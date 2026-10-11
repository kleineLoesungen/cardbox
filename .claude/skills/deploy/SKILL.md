---
name: deploy
description: Fester Ablauf für das Ausrollen: Vorbedingungen prüfen, Datenbank sichern, Abbild bauen, ausrollen, Rauchtest. Nur auf ausdrückliche Anweisung.
disable-model-invocation: true
argument-hint: [zielumgebung]
---

Ausrollen nach **$ARGUMENTS**.

Dieser Ablauf verändert ein laufendes System. Arbeite ihn der Reihe nach ab
und **halte an, sobald ein Schritt nicht sauber durchläuft**. Nichts
überspringen, weil es „beim letzten Mal auch ging".

## Aktueller Stand

!`git status --short && echo "--- Letzte Commits ---" && git log --oneline -3 && echo "--- Version ---" && head -n 14 CHANGELOG.md | tail -n 5`

## 1. Vorbedingungen

- [ ] `just check` ist grün. Ohne das gar nicht erst weiter.
- [ ] Alles committet, nichts Unversioniertes im Arbeitsverzeichnis.
- [ ] `CHANGELOG.md` hat einen Eintrag für diese Veröffentlichung. Die oberste
      Überschrift ist die Version, die die Instanz danach anzeigt.
- [ ] Umgebungsvariablen am Ziel gesetzt und geprüft:
      `APP_KEY` (eigener, nicht der aus compose.yaml), `APP_DEBUG=false`,
      `APP_URL` auf die echte Adresse, `SESSION_SECURE_COOKIE=true`,
      `TRUST_PROXY=true` hinter einem Reverse-Proxy, `DB_*`, `MAIL_*`,
      `APP_NAME`.
- [ ] Neue Migrationen durchgesehen: Laufen sie auch auf der **vorhandenen**
      Datenbank? Sind sie rückwärtsverträglich, falls kurzzeitig alte und
      neue Fassung gleichzeitig laufen?

Einzelheiten stehen in `docs/DEPLOY.md`.

## 2. Sichern — vor allem anderen

```bash
pg_dump "postgres://$DB_USERNAME:$DB_PASSWORD@$DB_HOST:$DB_PORT/$DB_DATABASE" \
  --format=custom --file=sicherung-$(date +%F-%H%M).dump
```

Die Sicherung erst als gelungen betrachten, wenn die Datei existiert und
plausibel groß ist. Migrationen laufen beim Start automatisch; ohne Sicherung
gibt es keinen Weg zurück.

## 3. Bauen

```bash
just build
```

Der Build prüft auch, was kein Test sieht — etwa ob Stylesheet, Icons und
`CHANGELOG.md` im Abbild landen.

## 4. Ausrollen

Nach dem Verfahren der Zielumgebung (siehe `docs/DEPLOY.md`). Bei mehreren
Instanzen: erst eine umstellen, prüfen, dann die übrigen. Der Containerstart
erledigt `config:cache`, `route:cache`, `view:cache` und die Migrationen.

## 5. Rauchtest — immer, nicht nur bei großen Änderungen

```bash
curl -fsS "$APP_URL/api/v1/health"
```

Dazu von Hand:

- [ ] Anmeldeseite lädt, Stylesheet greift
- [ ] Anmeldung mit einem echten Konto
- [ ] eine Seite mit Daten aus der Datenbank
- [ ] unter `/admin/system`: Version stimmt mit dem Changelog-Eintrag überein
- [ ] bei Mail-Änderungen: Testmail unter `/admin/system`

## 6. Melden

Nenne: ausgerollte Fassung, angewandte Migrationen, Ergebnis des Rauchtests
und wo die Sicherung liegt. Ging etwas schief, benenne es deutlich — und
erfinde keinen Erfolg.
