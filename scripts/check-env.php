<?php

/*
 * Finds .env values that Laravel would read differently from how they are
 * written — mostly an unquoted "#" (starts a comment) or spaces. Prints only
 * the names of the affected keys, never the values.
 *
 *   php scripts/check-env.php .env.production
 *
 * Exit code 1 if anything is off. Used by deploy.sh and deploy-webserver.sh.
 */

require __DIR__.'/../vendor/autoload.php';

$file = $argv[1] ?? '.env';
if (! is_readable($file)) {
    fwrite(STDERR, "{$file} nicht lesbar.\n");
    exit(1);
}

$problems = [];
foreach (file($file, FILE_IGNORE_NEW_LINES) as $number => $line) {
    if (! preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/', $line, $m)) {
        continue;
    }
    [, $key, $raw] = $m;
    $raw = rtrim($raw);
    // Quoted values and ${VAR} references are meant to be parsed.
    if ($raw === '' || $raw[0] === '"' || $raw[0] === "'" || str_contains($raw, '${')) {
        continue;
    }

    try {
        $parsed = Dotenv\Dotenv::parse("{$key}={$raw}")[$key] ?? null;
    } catch (Throwable) {
        $parsed = null;
    }

    if ($parsed !== $raw) {
        $problems[] = sprintf('  Zeile %d: %s', $number + 1, $key);
    }
}

if ($problems !== []) {
    fwrite(STDERR, "In {$file} würde Laravel diese Werte anders lesen, als sie dastehen (z. B. # oder Leerzeichen):\n"
        .implode("\n", $problems)."\n"
        ."Den Wert in einfache Anführungszeichen setzen: KEY='…'\n");
    exit(1);
}
