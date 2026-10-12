<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An optional short hint per card, e.g. "(Zug)" or "ugs.". Shown under the
 * question before flipping, to tell similar words apart.
 *
 * See docs/specs/lernplaene-und-karten.md, B2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('hint', 100)->nullable()->after('back');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn('hint');
        });
    }
};
