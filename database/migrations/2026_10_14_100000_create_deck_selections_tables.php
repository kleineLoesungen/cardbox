<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Learning only part of a deck (issue #10): a person can pause cards of any
 * deck they learn — e.g. follow a whole book and switch chapters on one by
 * one.
 *
 * * deck_selections: this person learns a selection of this deck. Without a
 *   row every card is active (as before).
 * * deck_selection_cards: the active cards of that selection. Cards the
 *   owner adds later are therefore inactive until switched on.
 *
 * Everything goes with the person, the deck or the card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deck_selections', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('deck_id')->constrained()->cascadeOnDelete();
            $table->timestampsTz();

            $table->primary(['user_id', 'deck_id']);
        });

        Schema::create('deck_selection_cards', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('deck_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('card_id')->constrained()->cascadeOnDelete();

            $table->primary(['user_id', 'card_id']);
            $table->index(['user_id', 'deck_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deck_selection_cards');
        Schema::dropIfExists('deck_selections');
    }
};
