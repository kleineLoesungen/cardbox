<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The whole schema up to version 2026.10.18 in one migration. It replaces the
 * twenty migrations from 0001_01_01_000000_create_users_table to
 * 2026_10_18_000000_add_round_size_to_learning_settings (squashed on
 * 2026-10-19); later changes are migrations of their own again.
 *
 * A database that already ran the old migrations up to 2026.10.18 has these
 * tables, so this one does nothing there and is only recorded as done. An
 * older one stops with a message instead: its missing columns would never
 * come otherwise. down() deliberately drops nothing — on a live database a
 * rollback would wipe everything.
 *
 * IDs are UUIDs (HasUuids in the models): they neither tell how many rows
 * there are nor can they be counted through.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            $this->ensureUpToDate();

            return;
        }

        // --- Accounts, sessions, cache -------------------------------------
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            // Argon2id, set through the 'hashed' cast.
            $table->string('password');
            // Order = rank, see App\Enums\Role::rank().
            $table->enum('role', ['user', 'moderator', 'admin'])->default('user');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            // Always stored lower-case, see App\Models\User. No e-mail addresses.
            $table->string('username', 30)->unique();
            // Self-registered accounts wait for an admin (UserService::register).
            $table->timestampTz('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();
        });
        // Separate step: a key on the table itself needs its primary key first.
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });

        // Sessions live in Postgres; the cookie only holds a random id.
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });

        // App tokens belong to the installation, not to a person (CLAUDE.md).
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('token_id')->unique();
            $table->string('token_hash');
            $table->jsonb('scopes')->default('[]');
            $table->foreignUuid('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        // Login throttle per username and per IP (App\Services\LoginThrottle).
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->string('attempt_key')->primary();
            $table->integer('failures')->default(0);
            $table->timestampTz('locked_until')->nullable();
            $table->timestampTz('last_attempt_at')->useCurrent()->index();
        });

        // The notes example from the technical base (docs/RECIPES.md).
        Schema::create('notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('body')->default('');
            $table->timestampsTz();

            $table->index(['user_id', 'created_at']);
        });

        // Small key/value store, e.g. the update check and the last reminder run.
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->timestampTz('updated_at')->useCurrent();
        });

        // --- Decks and cards -----------------------------------------------
        Schema::create('decks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            // App\Enums\Language codes; no foreign table, a new language needs no migration.
            $table->string('front_language', 10);
            $table->string('back_language', 10);
            $table->boolean('is_shared')->default(false);
            $table->string('copied_from_name', 100)->nullable();
            $table->timestampsTz();
            // The deck a copy was made from; no foreign key, the original may be deleted.
            $table->uuid('source_deck_id')->nullable();

            $table->index('user_id');
            $table->index('is_shared');
            $table->index('source_deck_id');
        });
        DB::statement('ALTER TABLE decks ADD CONSTRAINT decks_languages_differ CHECK (front_language <> back_language)');

        Schema::create('cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('deck_id')->constrained()->cascadeOnDelete();
            // Lists of meanings, the first one is the main meaning.
            $table->jsonb('front');
            $table->jsonb('back');
            $table->timestampsTz();
            $table->string('hint', 100)->nullable();
            // The card a copy was made from, and its state at that time.
            $table->uuid('source_card_id')->nullable();
            $table->timestampTz('source_updated_at')->nullable();

            $table->index('deck_id');
            $table->index('source_card_id');
        });

        Schema::create('deck_follows', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('deck_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->primary(['user_id', 'deck_id']);
            $table->index('deck_id');
        });

        // "Fehler melden": suggestions for other people's shared cards.
        Schema::create('card_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('card_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->jsonb('proposed_front')->nullable();
            $table->jsonb('proposed_back')->nullable();
            $table->string('comment', 300)->nullable();
            $table->string('status', 10)->default('open');
            $table->timestampTz('card_updated_at');
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();

            $table->index(['card_id', 'status']);
            $table->index('user_id');
        });
        // At most one open suggestion per card and person.
        DB::statement("CREATE UNIQUE INDEX card_suggestions_one_open ON card_suggestions (card_id, user_id) WHERE status = 'open'");

        // --- Learning --------------------------------------------------------
        // A deck learned with one plan; progress belongs to a run.
        Schema::create('deck_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('deck_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 10);
            $table->smallInteger('box_count');
            $table->jsonb('day_gaps');
            $table->timestampsTz();

            $table->unique(['user_id', 'deck_id', 'plan']);
            $table->index('deck_id');
        });

        Schema::create('card_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('card_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10);
            // 0 = new, box_count of the run = "Kann ich".
            $table->smallInteger('box')->default(0);
            $table->date('due_on')->nullable();
            $table->timestampTz('last_reviewed_at')->nullable();
            $table->integer('review_count')->default(0);
            $table->integer('correct_count')->default(0);
            $table->timestampsTz();
            $table->foreignUuid('deck_run_id')->constrained()->cascadeOnDelete();

            $table->unique(['deck_run_id', 'card_id', 'direction']);
            $table->index(['user_id', 'due_on']);
            $table->index('card_id');
        });

        Schema::create('learning_settings', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->smallInteger('box_count');
            $table->jsonb('day_gaps');
            $table->timestampsTz();
            $table->smallInteger('daily_goal')->default(20);
            // Daily reminder: local time (null = off) and the day it was handled.
            $table->time('reminder_time')->nullable();
            $table->date('reminder_sent_on')->nullable();
            $table->smallInteger('round_size')->default(20);
        });

        // One row per person and learning day (Europe/Berlin).
        Schema::create('study_days', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->integer('reviews')->default(0);
            $table->integer('correct')->default(0);
            // Cards known for the daily goal.
            $table->integer('answers')->default(0);
            $table->boolean('goal_met')->default(false);

            $table->primary(['user_id', 'day']);
        });

        // "Karten auswählen": a deck learned only in part (issue #10).
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

        // Per person and deck: "Auch rückwärts lernen", "Antworten eintippen".
        Schema::create('deck_preferences', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('deck_id')->constrained()->cascadeOnDelete();
            $table->boolean('with_reverse')->default(false);
            $table->timestampsTz();
            $table->boolean('type_answers')->default(false);

            $table->primary(['user_id', 'deck_id']);
        });

        // Devices that asked for the daily reminder; one device belongs to one account.
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint')->unique();
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->timestampsTz();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Das Basisschema lässt sich nicht zurückrollen – das würde alle Daten löschen.');
    }

    /** An existing database must have run the last of the squashed migrations. */
    private function ensureUpToDate(): void
    {
        $table = (string) config('database.migrations.table', 'migrations');
        $last = '2026_10_18_000000_add_round_size_to_learning_settings';
        if (! Schema::hasTable($table) || ! DB::table($table)->where('migration', $last)->exists()) {
            throw new RuntimeException("Die Datenbank ist älter als Version 2026.10.18 (es fehlt die Migration {$last}). Erst mit Version 2026.10.19.2 aktualisieren, dann mit dieser.");
        }
    }
};
