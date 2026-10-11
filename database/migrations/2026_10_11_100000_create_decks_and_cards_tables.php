<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decks (Stapel), their cards and who follows which shared deck.
 *
 * Every card belongs to exactly one deck, so visibility is a property of the
 * deck alone and everything can cascade with its owner — see
 * docs/specs/vokabelkarten.md, "Privat und geteilt".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->string('front_language', 10);
            $table->string('back_language', 10);
            $table->boolean('is_shared')->default(false);
            // Display only ("Kopie von …"); no foreign key, the original may vanish.
            $table->string('copied_from_name', 100)->nullable();
            $table->timestampsTz();

            $table->index('user_id');
            $table->index('is_shared');
        });

        DB::statement('ALTER TABLE decks ADD CONSTRAINT decks_languages_differ CHECK (front_language <> back_language)');

        Schema::create('cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('deck_id')->constrained()->cascadeOnDelete();
            // Lists of meanings, e.g. ["fast", "quick"].
            $table->jsonb('front');
            $table->jsonb('back');
            $table->timestampsTz();

            $table->index('deck_id');
        });

        Schema::create('deck_follows', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('deck_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->primary(['user_id', 'deck_id']);
            $table->index('deck_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deck_follows');
        Schema::dropIfExists('cards');
        Schema::dropIfExists('decks');
    }
};
