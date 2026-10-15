<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per person and deck: also learn back → front ("Auch rückwärts lernen",
 * docs/specs/einfacher-lernen.md). Without a row only front → back is
 * learned.
 *
 * Whoever already has backward progress in a deck gets the switch on, so no
 * due card disappears.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deck_preferences', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('deck_id')->constrained()->cascadeOnDelete();
            $table->boolean('with_reverse')->default(false);
            $table->timestampsTz();

            $table->primary(['user_id', 'deck_id']);
        });

        DB::statement(<<<'SQL'
            INSERT INTO deck_preferences (user_id, deck_id, with_reverse, created_at, updated_at)
            SELECT DISTINCT p.user_id, c.deck_id, true, now(), now()
            FROM card_progress p
            JOIN cards c ON c.id = p.card_id
            WHERE p.direction = 'backward'
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('deck_preferences');
    }
};
