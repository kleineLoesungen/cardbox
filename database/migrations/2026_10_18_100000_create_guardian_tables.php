<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parents see the progress (docs/specs/eltern-ansicht.md):
 *
 * * guardian_links: who may look at whose progress. Ends with either account.
 * * guardian_codes: at most one open code per child, stored as a hash only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_links', function (Blueprint $table) {
            $table->foreignUuid('guardian_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('child_id')->constrained('users')->cascadeOnDelete();
            $table->timestampTz('created_at');

            $table->primary(['guardian_id', 'child_id']);
            $table->index('child_id');
        });
        DB::statement('ALTER TABLE guardian_links ADD CONSTRAINT guardian_links_not_self CHECK (guardian_id <> child_id)');

        Schema::create('guardian_codes', function (Blueprint $table) {
            $table->foreignUuid('child_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_codes');
        Schema::dropIfExists('guardian_links');
    }
};
