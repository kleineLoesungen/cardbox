<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App-Tokens: Zugaenge fuer Maschinen, nicht fuer Menschen.
 *
 * Ausgegeben wird "sk_<token_id>_<secret>". Gespeichert wird nur token_id
 * (Klartext, zum Nachschlagen) und der SHA-256-Hash des Geheimnisses. Das
 * Geheimnis selbst ist nach der Ausgabe weg.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('token_id')->unique();
            $table->string('token_hash');
            // Was das Token darf. Siehe App\Enums\Scope.
            $table->jsonb('scopes')->default('[]');
            // Wer es ausgestellt hat — nur zur Nachvollziehbarkeit. SET NULL statt
            // CASCADE: Ein ausscheidender Administrator darf nicht die
            // Maschinen-Zugaenge mit ins Grab nehmen.
            $table->foreignUuid('created_by')->nullable()->index()
                ->constrained('users')->nullOnDelete();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
