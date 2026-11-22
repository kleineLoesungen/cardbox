<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * cards.origin_user_id: who wrote a card that came from another deck (copy,
 * "Aus anderen Stapeln übernehmen", import with origin). Inherited along
 * copies of copies, and without a foreign key so it survives the original
 * and its author being deleted. Decks with other people's cards are never
 * shared (docs/specs/eltern-konten.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->uuid('origin_user_id')->nullable();
            $table->index('origin_user_id');
        });

        // Direct originals first, then inherit along chains of copies.
        DB::statement('UPDATE cards c SET origin_user_id = d.user_id FROM cards s JOIN decks d ON d.id = s.deck_id WHERE c.source_card_id = s.id');
        // Original card gone: the deck the copy was made from still names
        // the author, as long as it exists.
        DB::statement('UPDATE cards c SET origin_user_id = sd.user_id FROM decks d JOIN decks sd ON sd.id = d.source_deck_id
            WHERE c.deck_id = d.id AND c.source_card_id IS NOT NULL AND c.origin_user_id IS NULL AND sd.user_id <> d.user_id');
        for ($i = 0; $i < 50; $i++) {
            $changed = DB::update('UPDATE cards c SET origin_user_id = s.origin_user_id FROM cards s
                WHERE c.source_card_id = s.id AND s.origin_user_id IS NOT NULL AND c.origin_user_id IS DISTINCT FROM s.origin_user_id');
            if ($changed === 0) {
                break;
            }
        }
    }

    public function down(): void
    {
        Schema::table('cards', fn (Blueprint $table) => $table->dropColumn('origin_user_id'));
    }
};
