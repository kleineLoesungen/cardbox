<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Learning: progress per person, card and direction; the person's box
 * plan; and the days on which they learned (for the streak).
 *
 * See docs/specs/vokabelkarten.md, "Karteikasten (Leitner)".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('card_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 10);
            // 0 = new, box_count = mastered ("Kann ich").
            $table->smallInteger('box')->default(0);
            // Calendar day in the learning time zone; null once mastered.
            $table->date('due_on')->nullable();
            $table->timestampTz('last_reviewed_at')->nullable();
            $table->integer('review_count')->default(0);
            $table->integer('correct_count')->default(0);
            $table->timestampsTz();

            $table->unique(['user_id', 'card_id', 'direction']);
            $table->index(['user_id', 'due_on']);
            $table->index('card_id');
        });

        Schema::create('learning_settings', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->smallInteger('box_count');
            $table->jsonb('day_gaps');
            $table->timestampsTz();
        });

        Schema::create('study_days', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->integer('reviews')->default(0);
            $table->integer('correct')->default(0);

            $table->primary(['user_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_days');
        Schema::dropIfExists('learning_settings');
        Schema::dropIfExists('card_progress');
    }
};
