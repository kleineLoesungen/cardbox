<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Antworten eintippen" per person and deck (docs/specs/schreiben-und-erinnern.md, A).
 * Off for everybody until switched on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deck_preferences', function (Blueprint $table) {
            $table->boolean('type_answers')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('deck_preferences', function (Blueprint $table) {
            $table->dropColumn('type_answers');
        });
    }
};
