<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bremse gegen das Durchprobieren von Passwoertern.
 *
 * Gezaehlt wird unter zwei Schluesseln gleichzeitig:
 *   "email:<adresse>"  bremst Angriffe auf EIN Konto, auch von vielen Rechnern
 *   "ip:<adresse>"     bremst EINEN Rechner, auch gegen viele Konten
 *
 * Beide werden vor jedem Anmeldeversuch geprueft; die laengere Sperre gewinnt.
 * Siehe App\Services\LoginThrottle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->string('attempt_key')->primary();
            $table->integer('failures')->default(0);
            $table->timestampTz('locked_until')->nullable();
            $table->timestampTz('last_attempt_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
