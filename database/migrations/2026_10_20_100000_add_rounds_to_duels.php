<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rounds of a duel (docs/specs/duell.md, "Runden"): one to five rounds of
 * ten words for the same players; after each round the ranking, then the
 * creator starts the next one.
 *
 * * duels.rounds / round: planned rounds and the one that is up.
 * * duels.round_finished_at: the round that is up is over and waits for the
 *   creator (null while it runs).
 * * duels.repeat_from_round: the first round in which words come again
 *   because the deck ran out (null: no repeats).
 * * duel_cards.round, card_id: all rounds are drawn when the duel is made;
 *   card_id is the deck card a question came from (no foreign key: the card
 *   may be deleted, the duel stays).
 * * duel_players.round_correct / round_time_ms: the round that is up;
 *   correct / time_ms stay the totals. answered: cards answered in all
 *   rounds — who answered none did not take part.
 * * duel_round_results: each player's result per finished round.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duels', function (Blueprint $table) {
            $table->smallInteger('rounds')->default(1);
            $table->smallInteger('round')->default(1);
            $table->timestampTz('round_finished_at')->nullable();
            $table->smallInteger('repeat_from_round')->nullable();
        });

        Schema::table('duel_cards', function (Blueprint $table) {
            $table->smallInteger('round')->default(1);
            $table->uuid('card_id')->nullable();
        });

        Schema::table('duel_players', function (Blueprint $table) {
            $table->smallInteger('round_correct')->default(0);
            $table->integer('round_time_ms')->default(0);
            $table->smallInteger('answered')->default(0);
        });

        Schema::create('duel_round_results', function (Blueprint $table) {
            $table->foreignUuid('player_id')->constrained('duel_players')->cascadeOnDelete();
            $table->smallInteger('round');
            $table->smallInteger('correct');
            $table->integer('time_ms');

            $table->primary(['player_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duel_round_results');

        Schema::table('duel_players', function (Blueprint $table) {
            $table->dropColumn(['round_correct', 'round_time_ms', 'answered']);
        });

        Schema::table('duel_cards', function (Blueprint $table) {
            $table->dropColumn(['round', 'card_id']);
        });

        Schema::table('duels', function (Blueprint $table) {
            $table->dropColumn(['rounds', 'round', 'round_finished_at', 'repeat_from_round']);
        });
    }
};
