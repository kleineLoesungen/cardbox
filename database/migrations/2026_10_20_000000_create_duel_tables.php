<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Duels (docs/specs/duell.md): ten words of a deck for up to ten players,
 * members or guests without an account.
 *
 * * duels: the duel with its code (keyed hash for the lookup, encrypted for
 *   showing it again) and the number of players.
 * * duel_cards: the ten questions, copied — later edits of the deck change
 *   nothing.
 * * duel_players: a member (user_id) or a guest (name and a secret link,
 *   stored as keyed hash), with progress and result.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('creator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('deck_id')->nullable()->constrained()->nullOnDelete();
            $table->string('deck_name', 100);
            $table->string('front_language', 10);
            $table->string('back_language', 10);
            $table->string('mode', 10);
            $table->smallInteger('player_count');
            $table->string('code_hash', 64)->unique();
            $table->text('code_encrypted');
            $table->timestampTz('expires_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('created_at');

            $table->index('creator_id');
            $table->index('created_at');
        });

        Schema::create('duel_cards', function (Blueprint $table) {
            $table->foreignUuid('duel_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('position');
            $table->text('question');
            $table->string('hint', 100)->nullable();
            $table->jsonb('answers');
            $table->jsonb('choices')->nullable();
            $table->smallInteger('correct_choice')->nullable();

            $table->primary(['duel_id', 'position']);
        });

        Schema::create('duel_players', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('duel_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_name', 20)->nullable();
            $table->string('guest_token_hash', 64)->nullable()->unique();
            $table->smallInteger('position')->default(0);
            $table->smallInteger('correct')->default(0);
            $table->integer('time_ms')->default(0);
            $table->timestampTz('shown_at')->nullable();
            $table->timestampTz('joined_at');
            $table->timestampTz('finished_at')->nullable();

            // Also the index for looking up a duel's players.
            $table->unique(['duel_id', 'user_id']);
        });
        // Either a member or a guest, never both or neither.
        DB::statement('ALTER TABLE duel_players ADD CONSTRAINT duel_players_member_or_guest CHECK ((user_id IS NULL) <> (guest_token_hash IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('duel_players');
        Schema::dropIfExists('duel_cards');
        Schema::dropIfExists('duels');
    }
};
