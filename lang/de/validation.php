<?php

/*
|--------------------------------------------------------------------------
| Deutsche Validierungsmeldungen
|--------------------------------------------------------------------------
|
| Nur die Regeln, die im Kit vorkommen, plus die haeufigsten. Fehlt eine,
| erscheint die englische Fassung (APP_FALLBACK_LOCALE=en). Eine neue Regel
| benutzt? Meldung hier ergaenzen.
|
| Feldnamen stehen unten unter 'attributes' — so heisst es „Titel" statt „title".
|
*/

return [

    'accepted' => ':Attribute muss akzeptiert werden.',
    'array' => ':Attribute muss eine Liste sein.',
    'boolean' => ':Attribute muss ja oder nein sein.',
    'confirmed' => ':Attribute stimmt nicht mit der Wiederholung überein.',
    'date' => ':Attribute ist kein gültiges Datum.',
    'email' => ':Attribute muss eine gültige E-Mail-Adresse sein.',
    'enum' => 'Der gewählte Wert für :attribute ist ungültig.',
    'exists' => 'Der gewählte Wert für :attribute existiert nicht.',
    'in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'integer' => ':Attribute muss eine ganze Zahl sein.',
    'max' => [
        'array' => ':Attribute darf höchstens :max Einträge haben.',
        'file' => ':Attribute darf höchstens :max Kilobyte groß sein.',
        'numeric' => ':Attribute darf höchstens :max sein.',
        'string' => ':Attribute darf höchstens :max Zeichen haben.',
    ],
    'min' => [
        'array' => ':Attribute muss mindestens :min Einträge haben.',
        'file' => ':Attribute muss mindestens :min Kilobyte groß sein.',
        'numeric' => ':Attribute muss mindestens :min sein.',
        'string' => ':Attribute muss mindestens :min Zeichen haben.',
    ],
    'numeric' => ':Attribute muss eine Zahl sein.',
    'password' => [
        'letters' => ':Attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => ':Attribute muss Groß- und Kleinbuchstaben enthalten.',
        'numbers' => ':Attribute muss mindestens eine Ziffer enthalten.',
        'symbols' => ':Attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => ':Attribute ist in einem Datenleck aufgetaucht. Bitte ein anderes wählen.',
    ],
    'required' => ':Attribute darf nicht leer sein.',
    'string' => ':Attribute muss Text sein.',
    'unique' => ':Attribute ist bereits vergeben.',
    'url' => ':Attribute muss eine gültige Adresse sein.',
    'uuid' => ':Attribute muss eine gültige UUID sein.',

    'custom' => [],

    'attributes' => [
        'body' => 'Text',
        'current_password' => 'Aktuelles Passwort',
        'email' => 'E-Mail-Adresse',
        'expires_in_days' => 'Gültigkeit',
        'name' => 'Name',
        'new_password' => 'Neues Passwort',
        'password' => 'Passwort',
        'role' => 'Rolle',
        'scopes' => 'Berechtigungen',
        'title' => 'Titel',
        'to' => 'Empfänger',
        'user_id' => 'Benutzer',
    ],

];
