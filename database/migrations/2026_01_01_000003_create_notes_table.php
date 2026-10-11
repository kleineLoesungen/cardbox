<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beispielressource. Zeigt das komplette Muster: Service, HTML-Routen mit htmx
 * und JSON-API. Zum Anpassen umbenennen oder loeschen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Immer mitnehmen: Besitzer mit CASCADE ...
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('body')->default('');
            $table->timestampsTz();

            // ... und den Index fuer "meine Eintraege, neueste zuerst".
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
