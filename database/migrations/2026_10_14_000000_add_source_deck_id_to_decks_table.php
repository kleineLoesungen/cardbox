<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which deck a deck was made from (copy in the app, or a ZIP import), so a
 * later import of the same set updates it instead of adding a duplicate.
 * No foreign key: the original may be gone or live on another installation.
 *
 * See docs/specs/stapel-export.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decks', function (Blueprint $table) {
            $table->uuid('source_deck_id')->nullable()->after('copied_from_name');
            $table->index('source_deck_id');
        });
    }

    public function down(): void
    {
        Schema::table('decks', function (Blueprint $table) {
            $table->dropIndex(['source_deck_id']);
            $table->dropColumn('source_deck_id');
        });
    }
};
