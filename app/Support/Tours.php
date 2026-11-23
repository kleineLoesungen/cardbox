<?php

namespace App\Support;

/**
 * The guided tours (docs/specs/gefuehrte-tour.md): per page at most three
 * steps for what people do not find by themselves.
 *
 * - The id is stored per person (users.tour_steps_seen) — never rename one;
 *   a changed step gets a new id and then shows again ("Neu: …").
 * - `target` is the value of a data-tour attribute in the page, never a CSS
 *   class; null shows the bubble in the middle. A step whose target is
 *   missing on the page waits until it is there.
 * - Title and text are fixed German strings. Driver.js renders them as HTML,
 *   so they never contain user input (the start script escapes them anyway).
 */
final class Tours
{
    /** @var array<string, list<array{id: string, target: string|null, title: string, text: string}>> */
    public const PAGES = [
        'heute' => [
            ['id' => 'heute.serie', 'target' => 'heute-serie', 'title' => 'Deine Woche', 'text' => 'Grün heißt: Tagesziel geschafft. Die Zahl zeigt, wie oft.'],
            ['id' => 'heute.los', 'target' => 'heute-los', 'title' => 'Alle Stapel gemischt', 'text' => 'Hier kommen Karten aus allen Stapeln, die du gerade lernst.'],
            ['id' => 'heute.lerne-ich', 'target' => 'heute-lerne-ich', 'title' => 'Nur diese Stapel kommen dran', 'text' => 'Einen Stapel pausierst du im Stapel selbst.'],
        ],
        'runde' => [
            ['id' => 'runde.vorlesen', 'target' => 'runde-vorlesen', 'title' => 'Vorlesen', 'text' => 'Tippe hier, dann hörst du, wie man das Wort spricht.'],
            ['id' => 'runde.ehrlich', 'target' => 'runde-ehrlich', 'title' => 'Sei ehrlich', 'text' => '„Nochmal“ holt die Karte bald wieder. So merkst du sie dir.'],
            ['id' => 'runde.aufhoeren', 'target' => 'runde-aufhoeren', 'title' => 'Jederzeit aufhören', 'text' => 'Was du gelernt hast, bleibt gespeichert.'],
        ],
        'stapel' => [
            ['id' => 'stapel.entdecken', 'target' => 'stapel-entdecken', 'title' => 'Stapel von anderen', 'text' => 'Hier findest du geteilte Stapel, auch nach Schulbuch (ISBN).'],
            ['id' => 'stapel.gruppen', 'target' => 'stapel-gruppen', 'title' => 'Lerne ich und Pausiert', 'text' => 'Pausierte Stapel kommen nicht unter „Heute“.'],
            ['id' => 'stapel.neu', 'target' => 'stapel-neu', 'title' => 'Eigener Stapel', 'text' => 'Zum Beispiel für die Vokabeln dieser Woche.'],
        ],
        'stapel-eigen' => [
            ['id' => 'stapel-eigen.menue', 'target' => 'stapel-eigen-menue', 'title' => 'Mehr im Menü', 'text' => 'Ganze Liste einfügen, Antworten eintippen, auch rückwärts lernen.'],
            ['id' => 'stapel-eigen.test', 'target' => 'stapel-eigen-test', 'title' => 'Vor der Arbeit', 'text' => 'Alle Karten, bis du jede einmal gewusst hast.'],
            ['id' => 'stapel-eigen.duell', 'target' => 'stapel-eigen-duell', 'title' => 'Freunde herausfordern', 'text' => 'Alle bekommen dieselben Wörter.'],
        ],
        'stapel-fremd' => [
            ['id' => 'stapel-fremd.lernen', 'target' => 'stapel-fremd-lernen', 'title' => 'Mitlernen', 'text' => 'Die Karten kommen unter „Heute“, Korrekturen kommen von selbst.'],
            ['id' => 'stapel-fremd.fehler', 'target' => 'stapel-fremd-fehler', 'title' => 'Fehler entdeckt?', 'text' => 'Karte antippen und melden.'],
            ['id' => 'stapel-fremd.fassung', 'target' => 'stapel-fremd-fassung', 'title' => 'Selbst ändern', 'text' => 'Unter ⋯ legst du eine eigene Fassung an.'],
        ],
        'woerterbuch' => [
            ['id' => 'woerterbuch.suche', 'target' => 'woerterbuch-suche', 'title' => 'Ohne Akzente', 'text' => '„eleve“ findet „élève“, „Strasse“ findet „Straße“.'],
            ['id' => 'woerterbuch.lernen', 'target' => 'woerterbuch-lernen', 'title' => 'Nur dieses Wort lernen', 'text' => 'Es kommt unter „Heute“ dran, ohne den ganzen Stapel.'],
            ['id' => 'woerterbuch.mehr', 'target' => 'woerterbuch-mehr', 'title' => 'Kopieren oder melden', 'text' => 'Unter „Mehr …“ kopierst du das Wort in einen eigenen Stapel oder meldest einen Fehler.'],
        ],
        'duelle' => [
            ['id' => 'duelle.code', 'target' => 'duelle-code', 'title' => 'Code bekommen?', 'text' => 'Hier eingeben und mitspielen.'],
            ['id' => 'duelle.gaeste', 'target' => null, 'title' => 'Auch ohne Konto', 'text' => 'Freunde ohne Konto spielen mit dem Code als Gast mit.'],
            ['id' => 'duelle.liste', 'target' => 'duelle-liste', 'title' => 'Rangliste', 'text' => 'Hier siehst du laufende und fertige Duelle.'],
        ],
        'konto' => [
            ['id' => 'konto.lernplan', 'target' => 'konto-lernplan', 'title' => 'Lernplan', 'text' => 'Wie oft Karten wiederkommen.'],
            ['id' => 'konto.runde', 'target' => 'konto-runde', 'title' => 'Kürzere Runden', 'text' => 'Weniger Karten pro Runde sind schneller geschafft.'],
            ['id' => 'konto.erinnerung', 'target' => 'konto-erinnerung', 'title' => 'Erinnerung', 'text' => 'Das Handy erinnert dich, wenn Karten warten.'],
        ],
        'kind' => [
            ['id' => 'kind.stapel', 'target' => 'kind-stapel', 'title' => 'Stapel bearbeiten', 'text' => 'Wie dein Kind. Teilst du einen mit dir selbst, lernst du mit.'],
            ['id' => 'kind.geteilt', 'target' => 'kind-geteilt', 'title' => 'Was andere schicken', 'text' => 'Stapel für dein Kind kannst du ablehnen.'],
            ['id' => 'kind.knifflig', 'target' => 'kind-knifflig', 'title' => 'Zum Abfragen', 'text' => 'Ganz unten: Wörter, die oft falsch waren.'],
        ],
        'teilen' => [
            ['id' => 'teilen.alle', 'target' => 'teilen-alle', 'title' => 'Für alle', 'text' => 'Alle finden den Stapel unter „Entdecken“.'],
            ['id' => 'teilen.personen', 'target' => 'teilen-personen', 'title' => 'Nur für bestimmte', 'text' => 'Zum Beispiel deine Klasse.'],
            ['id' => 'teilen.rechte', 'target' => 'teilen-rechte', 'title' => 'Nur Eigenes', 'text' => 'Karten von anderen lassen sich nicht weiterteilen.'],
        ],
        'admin' => [
            ['id' => 'admin.freigabe', 'target' => 'admin-freigabe', 'title' => 'Freigeben', 'text' => 'Alter wählen, bei unter 18 Eltern eintragen.'],
            ['id' => 'admin.eltern-fehlen', 'target' => 'admin-eltern-fehlen', 'title' => 'Eltern fehlen', 'text' => 'Ohne Eltern kann niemand für das Kind teilen.'],
            ['id' => 'admin.konto', 'target' => 'admin-konto', 'title' => 'Konto öffnen', 'text' => 'Dort änderst du Alter und Eltern und bestätigst „nur zuschauen“.'],
        ],
    ];

    /** @return list<string> every step id */
    public static function stepIds(): array
    {
        return array_merge(...array_values(array_map(fn (array $steps) => array_column($steps, 'id'), self::PAGES)));
    }
}
