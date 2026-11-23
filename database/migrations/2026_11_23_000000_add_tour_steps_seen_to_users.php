<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users.tour_steps_seen: ids of the tour steps a person has seen
 * (docs/specs/gefuehrte-tour.md). Existing accounts start empty and see
 * every tour once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->jsonb('tour_steps_seen')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('tour_steps_seen'));
    }
};
