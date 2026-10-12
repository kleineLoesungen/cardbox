<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deck runs ("Durchgänge"): a deck learned with one plan. Progress now
 * belongs to a run instead of directly to the person, so the same deck can
 * be learned with several plans at once. The plan is copied into the run
 * when it starts and never changes afterwards.
 *
 * Existing progress moves into one run per (person, deck) with the person's
 * current standard plan, so nothing changes for anybody.
 *
 * See docs/specs/lernplaene-und-karten.md, "A – Lernpläne je Stapel".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deck_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('deck_id')->constrained()->cascadeOnDelete();
            // normal | quick | thorough | custom
            $table->string('plan', 10);
            $table->smallInteger('box_count');
            $table->jsonb('day_gaps');
            $table->timestampsTz();

            $table->unique(['user_id', 'deck_id', 'plan']);
            $table->index('deck_id');
        });

        Schema::table('card_progress', function (Blueprint $table) {
            $table->foreignUuid('deck_run_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
        });

        // One run per (person, deck) with progress. The plan values are the
        // presets as they were when this migration was written — literal on
        // purpose, so later changes to App\Enums\StudyPlan do not alter it.
        // md5(...)::uuid instead of gen_random_uuid(): works on PostgreSQL < 13.
        DB::statement(<<<'SQL'
            INSERT INTO deck_runs (id, user_id, deck_id, plan, box_count, day_gaps, created_at, updated_at)
            SELECT md5(random()::text || clock_timestamp()::text || x.user_id::text || x.deck_id::text)::uuid,
                   x.user_id,
                   x.deck_id,
                   CASE
                       WHEN ls.user_id IS NULL THEN 'normal'
                       WHEN ls.box_count = 5 AND ls.day_gaps = '[1, 2, 4, 7]'::jsonb THEN 'normal'
                       WHEN ls.box_count = 4 AND ls.day_gaps = '[1, 1, 2]'::jsonb THEN 'quick'
                       WHEN ls.box_count = 6 AND ls.day_gaps = '[1, 2, 4, 7, 14]'::jsonb THEN 'thorough'
                       ELSE 'custom'
                   END,
                   COALESCE(ls.box_count, 5),
                   COALESCE(ls.day_gaps, '[1, 2, 4, 7]'::jsonb),
                   now(),
                   now()
            FROM (
                SELECT DISTINCT p.user_id, c.deck_id
                FROM card_progress p
                JOIN cards c ON c.id = p.card_id
            ) x
            LEFT JOIN learning_settings ls ON ls.user_id = x.user_id
            SQL);

        DB::statement(<<<'SQL'
            UPDATE card_progress p
            SET deck_run_id = r.id
            FROM cards c, deck_runs r
            WHERE c.id = p.card_id AND r.user_id = p.user_id AND r.deck_id = c.deck_id
            SQL);

        DB::statement('ALTER TABLE card_progress ALTER COLUMN deck_run_id SET NOT NULL');

        Schema::table('card_progress', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'card_id', 'direction']);
            $table->unique(['deck_run_id', 'card_id', 'direction']);
        });
    }

    /**
     * Lossy: with several runs per deck only the most recently reviewed
     * progress of each card and direction survives.
     */
    public function down(): void
    {
        DB::statement(<<<'SQL'
            DELETE FROM card_progress p
            USING card_progress q
            WHERE p.user_id = q.user_id AND p.card_id = q.card_id AND p.direction = q.direction
              AND (COALESCE(p.last_reviewed_at, p.created_at), p.id) < (COALESCE(q.last_reviewed_at, q.created_at), q.id)
            SQL);

        Schema::table('card_progress', function (Blueprint $table) {
            $table->dropUnique(['deck_run_id', 'card_id', 'direction']);
            $table->unique(['user_id', 'card_id', 'direction']);
            $table->dropConstrainedForeignId('deck_run_id');
        });

        Schema::dropIfExists('deck_runs');
    }
};
