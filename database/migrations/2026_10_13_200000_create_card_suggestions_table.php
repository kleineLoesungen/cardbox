<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Suggestions to the owner of a shared deck: "Fehler melden" on a card.
 * At most one open suggestion per person and card (partial unique index).
 * They go with their card and with the sender's account.
 *
 * See docs/specs/lernplaene-und-karten.md, B5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('card_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            // typo | wrong | missing | other
            $table->string('type', 20);
            $table->jsonb('proposed_front')->nullable();
            $table->jsonb('proposed_back')->nullable();
            $table->string('comment', 300)->nullable();
            // open | accepted | declined
            $table->string('status', 10)->default('open');
            // The card's updated_at when suggested: "changed since" for the owner.
            $table->timestampTz('card_updated_at');
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();

            $table->index(['card_id', 'status']);
            $table->index('user_id');
        });

        DB::statement("CREATE UNIQUE INDEX card_suggestions_one_open ON card_suggestions (card_id, user_id) WHERE status = 'open'");
    }

    public function down(): void
    {
        Schema::dropIfExists('card_suggestions');
    }
};
