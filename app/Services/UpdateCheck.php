<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Changelog;
use App\Support\ChangelogEntry;
use App\Support\UpdateStatus;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Abgleich mit dem Changelog im Repository.
 *
 * Die Anwendung lädt nichts herunter und installiert nichts. Sie liest eine
 * öffentlich lesbare Rohdatei und zeigt Administratoren, dass es eine neuere
 * Version gibt.
 *
 * Zwei Dinge halten das harmlos:
 *
 * * **Ohne `UPDATE_CHECK_URL` passiert nichts.** Das ist die Vorgabe im Kit —
 *   sonst würde jedes abgeleitete Projekt die Versionen des Kits anzeigen und
 *   ungefragt nach außen sprechen.
 * * **Ein Fehlschlag ist kein Fehler.** Keine Fehlerseite, kein Abbruch; der
 *   zuletzt bekannte Stand bleibt stehen und wird als veraltet gekennzeichnet.
 */
class UpdateCheck
{
    /** Schlüssel in der Tabelle `settings`. */
    private const CACHE_KEY = 'update_check';

    /** So lange gilt ein Abruf als frisch (Sekunden). */
    private const TTL = 24 * 60 * 60;

    /** Die Systemseite darf nie an einer langsamen Verbindung hängen (Sekunden). */
    private const TIMEOUT = 3;

    /** Mehr Einträge zeigt ohnehin niemand an. */
    private const MAX_ENTRIES = 100;

    /** Schutz vor einer Adresse, die etwas sehr Großes ausliefert. */
    private const MAX_BYTES = 256 * 1024;

    /**
     * Status für die Systemseite. Ruft höchstens alle 24 Stunden wirklich ab —
     * `$force` übergeht den Zwischenspeicher.
     */
    public function status(?string $url, bool $force = false): UpdateStatus
    {
        $installed = Changelog::installedVersion();
        $url = trim((string) $url);

        if ($url === '') {
            return new UpdateStatus(enabled: false, installed: $installed);
        }

        $cache = $this->cachedState();
        $fresh = $cache['url'] === $url && ! $this->isExpired($cache['checked_at']);

        if ($force || ! $fresh) {
            $fetched = $this->fetch($url);
            // Bei einem Fehlschlag den zuletzt bekannten Stand behalten — aber
            // nur, wenn er zur selben Adresse gehört.
            $previous = $cache['url'] === $url ? $cache['entries'] : [];

            $cache = [
                'url' => $url,
                'checked_at' => Carbon::now()->getTimestamp(),
                'ok' => $fetched !== null,
                'entries' => $fetched ?? $previous,
            ];

            try {
                Setting::write(self::CACHE_KEY, json_encode($cache, JSON_THROW_ON_ERROR));
            } catch (Throwable $e) {
                Log::warning('Update-Stand nicht speicherbar', ['error' => $e->getMessage()]);
            }
        }

        $entries = $this->toEntries($cache['entries']);
        $newer = $installed === null ? [] : array_values(array_filter(
            $entries,
            fn (ChangelogEntry $e) => Changelog::versionCompare($e->version, $installed) > 0,
        ));

        return new UpdateStatus(
            enabled: true,
            installed: $installed,
            latest: $entries[0]->version ?? null,
            newer: $newer,
            ok: $cache['ok'],
            checkedAt: $cache['checked_at'] > 0 ? Carbon::createFromTimestamp($cache['checked_at']) : null,
        );
    }

    /**
     * Zwischengespeichertes Ergebnis. Die Adresse steht mit darin, damit ein
     * Wechsel den alten Stand nicht als „neuere Version" ausgibt.
     *
     * @return array{url: string, checked_at: int, ok: bool, entries: list<mixed>}
     */
    private function cachedState(): array
    {
        $leer = ['url' => '', 'checked_at' => 0, 'ok' => false, 'entries' => []];

        try {
            $json = Setting::read(self::CACHE_KEY);
            $gelesen = $json === null ? [] : json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::warning('Update-Stand nicht lesbar', ['error' => $e->getMessage()]);

            return $leer;
        }

        if (! is_array($gelesen)) {
            return $leer;
        }

        /** @var array{url: string, checked_at: int, ok: bool, entries: list<mixed>} $stand */
        $stand = [
            'url' => is_string($gelesen['url'] ?? null) ? $gelesen['url'] : '',
            'checked_at' => is_int($gelesen['checked_at'] ?? null) ? $gelesen['checked_at'] : 0,
            'ok' => (bool) ($gelesen['ok'] ?? false),
            'entries' => is_array($gelesen['entries'] ?? null) ? array_values($gelesen['entries']) : [],
        ];

        return $stand;
    }

    private function isExpired(int $checkedAt): bool
    {
        $alter = Carbon::now()->getTimestamp() - $checkedAt;

        return $alter < 0 || $alter >= self::TTL;
    }

    /**
     * Holt die Rohdatei. `null` bei jedem Fehler — Netz weg, 404, Zeitlimit.
     *
     * @return list<array{version: string, items: list<string>, migrations: list<string>}>|null
     */
    private function fetch(string $url): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)
                ->connectTimeout(self::TIMEOUT)
                ->withHeader('User-Agent', 'starter-update-check/'.(Changelog::installedVersion() ?? 'unbekannt'))
                ->withOptions([
                    // Umleitungen nur zu `https`, höchstens drei.
                    //
                    // Die Prüfadresse bestimmt der Betreiber, das UMLEITUNGSZIEL
                    // aber die Gegenstelle. Ohne diese Regel führt ein
                    // `302 Location: http://169.254.169.254/…` dazu, dass der
                    // Server eine Anfrage aus dem internen Netz heraus stellt.
                    // Drei Sprünge, weil github.com/…/raw/… auf
                    // raw.githubusercontent.com umleitet.
                    'allow_redirects' => ['max' => 3, 'protocols' => ['https'], 'strict' => true, 'referer' => false],
                    // Antwort in Stücken lesen statt erst vollständig puffern.
                    'stream' => true,
                ])
                ->get($url);
        } catch (Throwable $e) {
            Log::info('Update-Prüfung nicht möglich', ['url' => $this->loggable($url), 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::info('Update-Prüfung: Gegenstelle antwortet mit Fehler', [
                'url' => $this->loggable($url),
                'status' => $response->status(),
            ]);

            return null;
        }

        $text = $this->body($response);
        if ($text === null) {
            return null;
        }

        $entries = array_slice(Changelog::parse($text), 0, self::MAX_ENTRIES);

        return array_map(fn (ChangelogEntry $e) => [
            'version' => $e->version,
            'items' => $e->items,
            'migrations' => $e->migrations,
        ], $entries);
    }

    /**
     * Liest den Antwortkörper in Stücken und bricht bei `MAX_BYTES` ab.
     *
     * Nicht einfach `$response->body()`: Das puffert erst alles und begrenzt
     * danach — eine Gegenstelle, die endlos liefert, füllt damit innerhalb des
     * Zeitlimits den Speicher.
     */
    private function body(Response $response): ?string
    {
        $psr = $response->toPsrResponse();

        $laenge = $psr->getHeaderLine('Content-Length');
        if (is_numeric($laenge) && (int) $laenge > self::MAX_BYTES) {
            Log::info('Update-Prüfung: Antwort ist zu groß');

            return null;
        }

        $stream = $psr->getBody();
        $rohdaten = '';

        while (! $stream->eof()) {
            $stueck = $stream->read(8192);
            if ($stueck === '') {
                break;
            }
            $rohdaten .= $stueck;

            if (strlen($rohdaten) > self::MAX_BYTES) {
                Log::info('Update-Prüfung: Antwort ist zu groß');

                return null;
            }
        }

        return $rohdaten;
    }

    /**
     * Macht aus dem gespeicherten JSON wieder Eintraege.
     *
     * Absichtlich misstrauisch: Der Inhalt stammt aus der Datenbank und wurde
     * dort aus einer fremden Datei befuellt. Was nicht passt, entfaellt still.
     *
     * @param  list<mixed>  $rows
     * @return list<ChangelogEntry>
     */
    private function toEntries(array $rows): array
    {
        $entries = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['version'] ?? null)) {
                continue;
            }

            $entries[] = new ChangelogEntry(
                $row['version'],
                $this->strings($row['items'] ?? []),
                $this->strings($row['migrations'] ?? []),
            );
        }

        return $entries;
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(strval(...), array_filter($value, is_scalar(...))));
    }

    /**
     * Adresse ohne Anmeldedaten und Query, für Logs.
     *
     * Wer trotz der Dokumentation ein Token in die Adresse schreibt, soll es
     * nicht im Betriebslog wiederfinden.
     */
    private function loggable(string $url): string
    {
        $teile = parse_url($url);
        if ($teile === false || ! isset($teile['host'])) {
            return '<unlesbare Adresse>';
        }

        $schema = isset($teile['scheme']) ? $teile['scheme'].'://' : '';

        return $schema.$teile['host'].($teile['path'] ?? '');
    }
}
