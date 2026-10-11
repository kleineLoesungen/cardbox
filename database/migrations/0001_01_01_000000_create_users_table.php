<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Benutzer und Sitzungen.
 *
 * IDs sind UUIDs (HasUuids im Modell): Sie verraten weder, wie viele Konten es
 * gibt, noch lassen sie sich durchzaehlen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Immer kleingeschrieben gespeichert, siehe App\Models\User.
            $table->string('email')->unique();
            $table->string('name');
            // Argon2id, gesetzt ueber den Cast 'hashed'.
            $table->string('password');
            // Reihenfolge = Rangfolge, siehe App\Enums\Role::rank().
            $table->enum('role', ['user', 'moderator', 'admin'])->default('user');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        // Sitzungen liegen in Postgres. Im Cookie steht nur eine zufaellige ID;
        // Abmelden wirkt dadurch serverseitig sofort.
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
