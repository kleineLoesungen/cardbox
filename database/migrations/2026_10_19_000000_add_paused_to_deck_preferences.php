<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Gerade nicht lernen" per person and deck (docs/specs/aktive-stapel-und-freie-karten.md, A).
 * Every deck stays active until paused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deck_preferences', function (Blueprint $table) {
            $table->boolean('paused')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('deck_preferences', function (Blueprint $table) {
            $table->dropColumn('paused');
        });
    }
};
