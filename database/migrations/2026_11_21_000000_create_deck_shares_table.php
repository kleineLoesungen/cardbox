<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A deck shared with single accounts (docs/specs/stapel-mit-einzelnen-teilen.md):
 * one row per person. "Accepted" is deck_follows; decks.is_shared stays
 * "with everybody". declined_at: the person declined — the row stays as a
 * block, so the deck cannot be pushed on them again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deck_shares', function (Blueprint $table) {
            $table->foreignUuid('deck_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('declined_at')->nullable();

            $table->primary(['deck_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deck_shares');
    }
};
