<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daily goal (docs/specs/motivierender-start.md, B):
 *
 * * learning_settings.daily_goal: answers a day the person wants to reach.
 * * study_days.answers: every answer of the day, in every kind of round;
 *   `reviews` keeps counting only the answers that move a box.
 * * study_days.goal_met: the day counts for the streak.
 *
 * Every day learned so far counts as met, so no streak breaks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_settings', function (Blueprint $table) {
            $table->smallInteger('daily_goal')->default(20);
        });

        Schema::table('study_days', function (Blueprint $table) {
            $table->integer('answers')->default(0);
            $table->boolean('goal_met')->default(false);
        });

        DB::table('study_days')->update(['answers' => DB::raw('reviews'), 'goal_met' => true]);
    }

    public function down(): void
    {
        Schema::table('study_days', function (Blueprint $table) {
            $table->dropColumn(['answers', 'goal_met']);
        });

        Schema::table('learning_settings', function (Blueprint $table) {
            $table->dropColumn('daily_goal');
        });
    }
};
