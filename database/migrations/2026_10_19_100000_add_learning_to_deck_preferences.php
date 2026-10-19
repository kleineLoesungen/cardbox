<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Lerne ich" per person and deck (docs/specs/aktive-stapel-und-freie-karten.md, A):
 *
 * * learning = true (also without a row): the deck is on "Heute".
 * * paused = true: "pausiert", not on "Heute".
 * * learning = false: an own deck the person does not learn right now.
 *
 * Existing own decks the owner never learned (no progress) become "not
 * learning"; own decks with cards in the boxes and followed decks stay on
 * "Heute", paused ones stay paused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deck_preferences', function (Blueprint $table) {
            $table->boolean('learning')->default(true);
        });

        DB::statement(<<<'SQL'
            INSERT INTO deck_preferences (user_id, deck_id, learning, created_at, updated_at)
            SELECT d.user_id, d.id, false, now(), now()
            FROM decks d
            WHERE NOT EXISTS (
                SELECT 1 FROM card_progress p JOIN cards c ON c.id = p.card_id
                WHERE c.deck_id = d.id AND p.user_id = d.user_id
            )
            ON CONFLICT (user_id, deck_id) DO UPDATE SET learning = false
                WHERE NOT deck_preferences.paused
            SQL);
    }

    public function down(): void
    {
        Schema::table('deck_preferences', function (Blueprint $table) {
            $table->dropColumn('learning');
        });
    }
};
