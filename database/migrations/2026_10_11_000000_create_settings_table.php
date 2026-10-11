<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allgemeiner Schluessel-Wert-Speicher fuer Werte, die zur Laufzeit entstehen
 * und keine eigene Tabelle rechtfertigen.
 *
 * Erster Eintrag: 'update_check' — Ergebnis des Abgleichs mit UPDATE_CHECK_URL.
 *
 * Alles, wonach gesucht, sortiert oder gefiltert wird, gehoert dagegen in eine
 * eigene Tabelle mit eigenen Spalten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
