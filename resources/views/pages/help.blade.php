{{--
  "So funktioniert der Karteikasten" — short help in plain language.
  The drawing follows the person's own plan (number of boxes, intervals).

  @param array{box_count: int, day_gaps: list<int>} $plan
--}}
@extends('layouts.app')
@section('title', 'Hilfe')

@section('main')
@php($n = $plan['box_count'])
@php($step = 64)
@php($width = ($n + 1) * $step)
<div class="mx-auto max-w-md stack gap-6">
  <div>
    <h1 class="text-2xl font-semibold tracking-tight">So funktioniert der Karteikasten</h1>
    <p class="mt-2 text-muted">
      Jede Karte liegt in einem Fach. Neue Karten liegen ganz links. Weißt du
      eine Karte, rückt sie ein Fach weiter und kommt erst nach ein paar Tagen
      wieder. Weißt du sie nicht, geht sie zurück in Fach 1.
    </p>
  </div>

  {{-- Drawing: Neu · Fach 1 … N−1 · Kann ich, with "gewusst" arrows above
       and the way back to Fach 1 below. --}}
  <figure class="card">
    <div class="card-body">
      <svg viewBox="0 0 {{ $width }} 160" class="w-full text-content" role="img"
           aria-labelledby="box-drawing-title">
        <title id="box-drawing-title">
          Fächer von Neu bis Kann ich. Gewusst: ein Fach weiter. Nicht gewusst: zurück in Fach 1.
        </title>
        <defs>
          {{-- Markers do not inherit the path colour, so one per colour. --}}
          <marker id="arrow-ok" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
            <path d="M0 0 L10 5 L0 10 z" class="fill-success" />
          </marker>
          <marker id="arrow-back" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
            <path d="M0 0 L10 5 L0 10 z" class="fill-danger" />
          </marker>
        </defs>

        @for ($b = 0; $b <= $n; $b++)
          @php($x = $b * $step + 6)
          <rect x="{{ $x }}" y="50" width="{{ $step - 12 }}" height="54" rx="6"
                class="{{ $b === $n ? 'fill-success-subtle stroke-success' : 'fill-surface-sunken stroke-border-strong' }}" stroke-width="1.5" />
          <text x="{{ $x + ($step - 12) / 2 }}" y="{{ $b >= 1 && $b < $n ? 74 : 82 }}" text-anchor="middle" font-size="16" font-weight="600"
                class="{{ $b === $n ? 'fill-success' : 'fill-current' }}">
            {{ $b === 0 ? 'Neu' : ($b === $n ? '✓' : $b) }}
          </text>
          @if ($b >= 1 && $b < $n)
            {{-- Interval of this box, inside the box so nothing crosses it. --}}
            <text x="{{ $x + ($step - 12) / 2 }}" y="95" text-anchor="middle" font-size="11" class="fill-muted">
              {{ $plan['day_gaps'][$b - 1] ?? '' }} {{ ($plan['day_gaps'][$b - 1] ?? 0) === 1 ? 'Tag' : 'Tage' }}
            </text>
          @endif
          @if ($b < $n)
            {{-- "gewusst": arc to the next box --}}
            <path d="M {{ $x + ($step - 12) / 2 }} 48 Q {{ $x + $step / 2 + 26 }} 18 {{ $x + $step + ($step - 12) / 2 - 4 }} 46"
                  fill="none" stroke-width="1.5" class="stroke-success" marker-end="url(#arrow-ok)" />
          @endif
        @endfor
        <text x="{{ $width / 2 }}" y="14" text-anchor="middle" font-size="13" class="fill-success">gewusst → ein Fach weiter</text>

        {{-- "nicht gewusst": from the last learning box back to box 1 (needs ≥ 2 learning boxes) --}}
        @if ($n >= 3)
        <path d="M {{ ($n - 1) * $step + $step / 2 }} 106 Q {{ ($n / 2) * $step + $step / 2 }} 150 {{ $step + $step / 2 }} 108"
              fill="none" stroke-width="1.5" stroke-dasharray="4 3" class="stroke-danger" marker-end="url(#arrow-back)" />
        @endif
        <text x="{{ $width / 2 }}" y="156" text-anchor="middle" font-size="13" class="fill-danger">nicht gewusst → zurück in Fach 1</text>
      </svg>
      {{-- The intervals as text: always for screen readers, visible when the
           drawing gets too small to read (many boxes). --}}
      <ul class="{{ $n > 6 ? 'mt-3 flex flex-wrap gap-x-3 gap-y-1 text-sm' : 'sr-only' }}">
        @for ($b = 1; $b < $n; $b++)
          @php($days = $plan['day_gaps'][$b - 1] ?? 1)
          <li>Fach {{ $b }}: {{ $days === 1 ? '1 Tag' : $days.' Tage' }}</li>
        @endfor
        <li>Fach {{ $n }}: Kann ich</li>
      </ul>
      <figcaption class="mt-2 text-sm text-muted">
        In jedem Fach steht, nach wie vielen Tagen die Karte wiederkommt.
        Ändern kannst du das im <a href="/learn/settings" class="text-brand hover:underline">Lernplan</a>.
      </figcaption>
    </div>
  </figure>

  <section class="stack gap-2">
    <h2 class="text-lg font-semibold">So lernst du</h2>
    <ol class="list-decimal stack gap-1 pl-5 text-muted">
      <li>Unter <strong>Stapel → Entdecken</strong> den Stapel suchen, den dir jemand geteilt hat – oder einen eigenen anlegen.</li>
      <li>Im Stapel auf <strong>Lernen</strong> tippen.</li>
      <li>Jeden Tag unter <strong>Heute</strong> auf <strong>Los geht's</strong> tippen. Dort kommen alle Karten, die dran sind, und jeden Tag bis zu 20 neue.</li>
    </ol>
    <p class="text-muted">
      Lies die Karte, überlege die Antwort und tippe auf <strong>Umdrehen</strong>.
      Dann sag ehrlich: <strong>Gewusst</strong> oder <strong>Nochmal</strong> –
      oder wisch die Karte nach rechts oder links. Was du nicht wusstest, kommt
      am Ende der Runde noch einmal. Hast du alles geschafft und willst mehr,
      tippe auf <strong>Noch 20 neue Karten</strong>.
    </p>
  </section>

  <section class="stack gap-2">
    <h2 class="text-lg font-semibold">Neue Wörter</h2>
    <p class="text-muted">
      Ein neues Wort zeigt dir die App zuerst ganz – vorne und hinten, zum
      Anhören. Tippe auf <strong>Weiter</strong>. Ein paar Karten später fragt
      sie dich danach.
    </p>
  </section>

  <section class="stack gap-2">
    <h2 class="text-lg font-semibold">Tagesziel und Lernserie</h2>
    <p class="text-muted">
      Der Ring unter <strong>Heute</strong> zeigt, wie viele Karten du heute
      gewusst hast – beim Lernen und beim Test üben. Eine Karte, die du erst
      bei der Wiederholung weißt, zählt auch; „Nochmal“ allein zählt nicht. Ist er voll,
      hast du dein <strong>Tagesziel</strong> erreicht – weiterlernen darfst
      du trotzdem. Wie groß das Ziel ist (10, 20 oder 30 Karten), stellst du
      unter <strong>Mein Konto → Lernplan</strong> ein.
    </p>
    <p class="text-muted">
      Jeder Tag mit erreichtem Ziel verlängert deine <strong>Lernserie</strong>.
      Gab es an einem Tag weniger zu lernen und du hast alles geschafft, zählt
      er auch. Ist gar nichts dran, üb ein paar Karten in einem Stapel – dann
      zählt der Tag ebenfalls.
    </p>
  </section>

  <section class="stack gap-2">
    <h2 class="text-lg font-semibold">Bald ist ein Test?</h2>
    <p class="text-muted">
      Öffne den Stapel und tippe auf <strong>Für den Test üben</strong>. Dann
      kommen alle Karten, bis du jede einmal gewusst hast. Das zählt nicht für
      den Karteikasten – deine tägliche Wiederholung läuft normal weiter.
    </p>
    <p class="text-muted">
      Mit <strong>Nur diesen Stapel lernen</strong> lernst du außerdem neue
      Karten aus diesem einen Stapel, ohne auf die anderen zu warten.
    </p>
  </section>

  <section class="stack gap-2">
    <h2 class="text-lg font-semibold">Auch rückwärts lernen</h2>
    <p class="text-muted">
      Normal fragt die App von vorne nach hinten, zum Beispiel Deutsch →
      Englisch. Soll sie auch Englisch → Deutsch fragen, schalte im Stapel
      unter <strong>⋯ → Auch rückwärts lernen</strong> ein.
    </p>
  </section>

  <section class="stack gap-2">
    <h2 class="text-lg font-semibold">Stapel von anderen lernen</h2>
    <p class="text-muted">
      Hat jemand einen Stapel mit allen geteilt, tippst du auf <strong>Lernen</strong>.
      Korrigiert die Person einen Fehler, siehst du das sofort. Ändern kannst
      du den Stapel nicht.
    </p>
    <p class="text-muted">
      Willst du selbst etwas ändern, legst du unter <strong>⋯ → Eigene Fassung
      anlegen</strong> eine Kopie an. Dein bisheriger Lernstand kommt mit.
      Ändert die Person später etwas am Original, zeigt dein Stapel das an –
      du entscheidest, ob du es übernimmst.
    </p>
  </section>

  <section class="stack gap-2">
    <h2 class="text-lg font-semibold">Lernplan</h2>
    <p class="text-muted">
      Wie oft Karten wiederkommen, stellst du unter <strong>Mein Konto →
      Lernplan</strong> ein. Er gilt für Stapel, die du neu beginnst. Einen
      begonnenen Stapel setzt du im Stapel-Menü mit <strong>Fortschritt
      zurücksetzen</strong> zurück, dann beginnt er mit deinem jetzigen Plan neu.
    </p>
  </section>

  <section class="stack gap-2">
    <h2 class="text-lg font-semibold">Nur einen Teil lernen</h2>
    <p class="text-muted">
      Ein Stapel umfasst ein ganzes Buch, du bist aber erst bei Kapitel 3? Im
      Stapel unter <strong>⋯ → Karten auswählen</strong> hakst du an, was du
      lernen willst – mit <strong>bis hier</strong> geht das in einem Tipp.
      Die anderen Karten sind pausiert: Sie kommen nicht dran, ihr Lernstand
      bleibt. Neue Karten, die später dazukommen, bleiben ebenfalls pausiert,
      bis du sie anhakst.
    </p>
  </section>

  <section class="stack gap-2">
    <h2 class="text-lg font-semibold">Wörterbuch</h2>
    <p class="text-muted">
      Unter <strong>Wörterbuch</strong> schlägst du ein Wort nach, ohne
      zu lernen – in deinen Stapeln und in allen geteilten. Du siehst die
      Übersetzung und kannst sie anhören. <strong>Lernen</strong> nimmt das
      Wort in deine Runde unter Heute auf – aus seinem Stapel erst einmal nur
      dieses eine. Unter <strong>Mehr …</strong> kannst du eine Kopie in einen
      eigenen Stapel legen und, steht das Wort in deinem eigenen Stapel, die
      Karte bearbeiten oder löschen – steht es in mehreren, wählst du einen
      Stapel oder alle.
    </p>
  </section>

  <section class="stack gap-2">
    <h2 class="text-lg font-semibold">Fehler gefunden?</h2>
    <p class="text-muted">
      Tippe in einem geteilten Stapel oder im Wörterbuch auf die Karte und dann auf
      <strong>Fehler melden</strong>. Schreib, wie es richtig wäre. Die Person,
      der der Stapel gehört, sieht deinen Vorschlag und kann die Karte für
      alle ändern. Ob sie ihn übernommen hat, siehst du an der Karte.
    </p>
  </section>

  <section class="stack gap-2">
    <h2 class="text-lg font-semibold">Mehrere Bedeutungen und Hinweise</h2>
    <p class="text-muted">
      Ein Wort kann mehrere Bedeutungen haben. Die oberste ist die
      <strong>Hauptbedeutung</strong>: Sie wird gefragt und vorgelesen, die
      anderen stehen klein darunter. Mit ☆ beim Bearbeiten machst du eine
      andere zur Hauptbedeutung.
    </p>
    <p class="text-muted">
      Ein <strong>Hinweis</strong> wie „(Zug)“ hilft, ähnliche Wörter
      auseinanderzuhalten. Er ist schon vor dem Umdrehen zu sehen – schreib
      also nicht die Lösung hinein.
    </p>
    <p class="text-muted">
      Teile in <strong>Klammern</strong> sind beim Eintippen freiwillig: Bei
      „(to) miss“ ist „miss“ richtig und „to miss“ auch, bei „colo(u)r“ beides,
      „color“ und „colour“.
    </p>
  </section>

  <section class="stack gap-2">
    <h2 class="text-lg font-semibold">Antworten eintippen</h2>
    <p class="text-muted">
      Im Vokabeltest musst du die Wörter schreiben. Schalte im Stapel unter
      <strong>⋯ → Antworten eintippen</strong> ein, dann tippst du die Antwort,
      statt die Karte umzudrehen. Groß- und Kleinschreibung ist egal, jede
      Bedeutung zählt. Bei einem kleinen Tippfehler sagt die App
      <strong>Fast!</strong> und zeigt, wie man es schreibt – beim Lernen zählt
      das als gewusst, beim Test üben kommt das Wort noch einmal.
    </p>
  </section>

  <section class="stack gap-2">
    <h2 class="text-lg font-semibold">Viele Karten auf einmal</h2>
    <p class="text-muted">
      Im Stapel unter <strong>⋯ → Liste einfügen</strong>: eine Karte pro Zeile,
      zum Beispiel <code class="code">Haus = house</code>. Du kannst auch zwei
      Spalten aus Excel, Word oder Numbers kopieren und einfügen. Vor dem
      Speichern siehst du eine Vorschau.
    </p>
    <p class="text-muted">
      Größere Änderungen gehen auch in einer Tabelle: <strong>⋯ → Als CSV
      exportieren</strong>, die Datei in Excel oder Numbers bearbeiten und über
      <strong>Liste einfügen</strong> wieder hochladen. Geänderte Karten behalten
      ihren Lernstand, neue Zeilen werden neue Karten, gelöscht wird nichts.
      Mehrere Bedeutungen trennst du mit <code class="code">|</code>.
      <a href="#csv" class="text-brand hover:underline">So sieht die Datei aus</a>.
    </p>
  </section>

  <section class="stack gap-2 scroll-mt-20" id="csv">
    <h2 class="text-lg font-semibold">Tabellen (CSV)</h2>
    <p class="text-muted">
      <strong>⋯ → Als CSV exportieren</strong> lädt einen Stapel als Datei
      herunter. Sie öffnet sich in Excel, Numbers und LibreOffice. Bearbeitet
      zurück kommt sie über <strong>⋯ → Liste einfügen → Oder eine Datei
      wählen</strong>. Vor dem Speichern zeigt eine Vorschau, was neu ist, was
      sich ändert und was gleich bleibt.
    </p>
    <pre class="code-block">ID;de;en;Hinweis
3f2a…;schnell;fast | quick;
7c1b…;verpassen;miss;(Zug)
;Bahnhof;station;</pre>
    <ul class="list-disc stack gap-1 pl-5 text-muted">
      <li><strong>Erste Zeile:</strong> <code class="code">ID</code>, dann die Sprachen der beiden Seiten als Kürzel, dann freiwillig <code class="code">Hinweis</code>. Statt der Kürzel geht auch <code class="code">Vorne;Hinten</code>.</li>
      <li><strong>ID:</strong> Mit ID wird die vorhandene Karte geändert, ihr Lernstand bleibt. Ohne ID entsteht eine neue Karte. IDs nie selbst ausdenken – leer lassen.</li>
      <li><strong>Mehrere Bedeutungen</strong> in eine Zelle, getrennt mit <code class="code">|</code>. Die erste ist die Hauptbedeutung.</li>
      <li><strong>Nichts wird gelöscht:</strong> Fehlt eine Karte in der Datei, bleibt sie im Stapel. Löschen geht nur in der App.</li>
      <li><strong>Sprachen andersherum</strong> (z. B. <code class="code">en;de</code>) sind kein Problem, die Seiten werden getauscht. Andere Sprachen als die des Stapels lehnt der Import ab.</li>
      <li><strong>Trennzeichen:</strong> Semikolon, Komma oder Tabulator – je nachdem, wie dein Programm speichert. Beim Speichern in Excel „CSV UTF-8“ wählen, damit Umlaute erhalten bleiben.</li>
      <li><strong>Ein Apostroph am Anfang</strong> (<code class="code">'=1+1</code>) schützt vor Formeln; er wird beim Import wieder entfernt.</li>
      <li><strong>Grenzen:</strong> höchstens {{ config('learning.import_max_lines') }} Zeilen und {{ config('learning.import_max_kilobytes') }} KB je Datei.</li>
    </ul>
    <p class="text-muted">
      <strong>Mehrere Stapel auf einmal:</strong> unter <strong>Stapel → Meine →
      Stapel exportieren</strong> Stapel ankreuzen und als ZIP-Datei laden. Darin
      steckt je Stapel eine solche Tabelle und eine Übersicht
      <code class="code">stapel.csv</code> mit Name, Beschreibung und Sprachen.
      <strong>Stapel importieren</strong> nimmt die ZIP-Datei wieder an: Deine
      Stapel werden aktualisiert, alle anderen kommen als neue private Stapel
      dazu. So kannst du ein ganzes Set sichern oder weitergeben.
    </p>
    <details>
      <summary class="cursor-pointer text-sm text-brand">Alle Sprachkürzel</summary>
      <table class="mt-2 text-sm">
        @foreach (\App\Enums\Language::cases() as $lang)
          <tr><td class="pr-4"><code class="code">{{ $lang->value }}</code></td><td class="text-muted">{{ $lang->label() }}</td></tr>
        @endforeach
      </table>
    </details>
  </section>

  {{--
    Read-aloud uses the voices installed on the device, not a server. If a
    voice is missing, the button does not appear; the round links here.
    Menu names differ a little between device versions — kept general.
  --}}
  <section class="stack gap-2 scroll-mt-20" id="vorlesen">
    <h2 class="text-lg font-semibold">Vorlesen funktioniert nicht?</h2>
    <p class="text-muted">
      Cardbox liest mit den Stimmen deines Geräts vor. Fehlt eine Stimme für eine
      Sprache, erscheint der Knopf <strong>Vorlesen</strong> nicht. Dann hilft
      meistens, die Stimme einmal herunterzuladen. Die Menüs heißen je nach Gerät
      etwas anders.
    </p>

    <details class="card">
      <summary class="card-body cursor-pointer font-medium">iPhone und iPad</summary>
      <ol class="list-decimal space-y-1 px-5 pb-4 pl-10 text-muted">
        <li><em>Einstellungen</em> öffnen → <em>Bedienungshilfen</em> → <em>Gesprochene Inhalte</em> → <em>Stimmen</em>.</li>
        <li>Die Sprache wählen, zum Beispiel <em>Englisch</em>, und eine Stimme laden (Wolke mit Pfeil).</li>
        <li>Cardbox in Safari neu laden.</li>
        <li>Hörst du trotzdem nichts: Lautstärke hochdrehen und den Stummschalter an der Seite ausschalten.</li>
      </ol>
    </details>

    <details class="card">
      <summary class="card-body cursor-pointer font-medium">Android</summary>
      <ol class="list-decimal space-y-1 px-5 pb-4 pl-10 text-muted">
        <li><em>Einstellungen</em> öffnen und nach <em>Sprachausgabe</em> oder <em>Text-in-Sprache</em> suchen (bei Samsung unter <em>Allgemeine Verwaltung</em>).</li>
        <li>Als Modul <em>Sprachausgabe von Google</em> wählen.</li>
        <li>Über das Zahnrad → <em>Sprachdaten installieren</em> die Sprache herunterladen.</li>
        <li>Chrome ganz schließen und Cardbox neu öffnen.</li>
      </ol>
    </details>

    <details class="card">
      <summary class="card-body cursor-pointer font-medium">Computer (Mac und Windows)</summary>
      <ul class="list-disc space-y-1 px-5 pb-4 pl-10 text-muted">
        <li><strong>Mac:</strong> <em>Systemeinstellungen</em> → <em>Bedienungshilfen</em> → <em>Gesprochene Inhalte</em> → bei <em>Systemstimme</em> weitere Stimmen laden.</li>
        <li><strong>Windows:</strong> <em>Einstellungen</em> → <em>Zeit und Sprache</em> → <em>Sprache</em> → Sprache hinzufügen und dabei <em>Sprachausgabe</em> mit auswählen.</li>
        <li>Danach den Browser neu starten.</li>
      </ul>
    </details>

    <p class="text-sm text-muted">
      Für Latein gibt es auf den meisten Geräten keine Stimme. Bei Rechenkarten
      (<em>Aufgabe</em> und <em>Ergebnis</em>) wird nie vorgelesen.
    </p>
  </section>

  <section class="stack gap-2">
    <h2 class="text-lg font-semibold">Als App auf den Startbildschirm</h2>
    <p class="text-muted">
      <strong>iPhone und iPad:</strong> In Safari unten auf <em>Teilen</em> tippen
      (Viereck mit Pfeil). Dann <em>Zum Home-Bildschirm</em> wählen.
    </p>
    <p class="text-muted">
      <strong>Android:</strong> In Chrome oben rechts auf <em>⋮</em> tippen. Dann
      <em>App installieren</em> oder <em>Zum Startbildschirm hinzufügen</em> wählen.
    </p>
  </section>
</div>
@endsection
