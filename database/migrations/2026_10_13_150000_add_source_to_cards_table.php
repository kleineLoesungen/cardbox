<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a copied card came from: the original card and how it looked when
 * copied (its updated_at). When the original changes later, the owner of
 * the copy is offered the update.
 *
 * Deliberately no foreign key: the original may be deleted, the copy stays.
 *
 * See docs/specs/lernplaene-und-karten.md, B3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->uuid('source_card_id')->nullable()->after('hint');
            $table->timestampTz('source_updated_at')->nullable()->after('source_card_id');

            $table->index('source_card_id');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex(['source_card_id']);
            $table->dropColumn(['source_card_id', 'source_updated_at']);
        });
    }
};
