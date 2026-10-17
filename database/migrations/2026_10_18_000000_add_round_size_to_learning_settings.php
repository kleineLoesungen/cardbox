<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cards per round, chosen under "Mein Konto" (docs/specs/runden-und-knifflige-woerter.md).
 * 20 as before for everybody.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_settings', function (Blueprint $table) {
            $table->smallInteger('round_size')->default(20);
        });
    }

    public function down(): void
    {
        Schema::table('learning_settings', function (Blueprint $table) {
            $table->dropColumn('round_size');
        });
    }
};
