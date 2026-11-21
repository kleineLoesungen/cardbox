<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parent accounts (docs/specs/eltern-konten.md):
 *
 * * users.is_adult: "ab 18", set by an admin. Existing accounts count as
 *   under 18, except admins and moderators — somebody has to be able to
 *   share and to assign parents.
 * * decks.share_review_pending: decks of accounts under 18 that are shared
 *   already; a parent looks at them once.
 * * deck_shares.shared_by: who added the person (owner or parent).
 * * guardian_links.linked_by / confirmed_at: the admin who assigned the
 *   parent, and when. Links from the time of codes stay without
 *   confirmed_at: the child agreed to being watched, not to edits, so they
 *   keep the overview only until an admin confirms them. Links to admins
 *   and moderators (now 18 or older) end.
 * * guardian_codes: gone — admins assign parents now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_adult')->default(false);
        });
        DB::table('users')->whereIn('role', ['admin', 'moderator'])->update(['is_adult' => true]);

        Schema::table('decks', function (Blueprint $table) {
            $table->boolean('share_review_pending')->default(false);
        });
        DB::table('decks')
            ->whereIn('user_id', DB::table('users')->where('is_adult', false)->select('id'))
            ->where(fn ($q) => $q
                ->where('is_shared', true)
                ->orWhereExists(fn ($e) => $e->selectRaw('1')->from('deck_shares')->whereColumn('deck_shares.deck_id', 'decks.id')->whereNull('deck_shares.declined_at')))
            ->update(['share_review_pending' => true]);

        Schema::table('deck_shares', function (Blueprint $table) {
            $table->foreignUuid('shared_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('guardian_links', function (Blueprint $table) {
            $table->foreignUuid('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
        });
        DB::table('guardian_links')
            ->whereIn('child_id', DB::table('users')->where('is_adult', true)->select('id'))
            ->delete();

        Schema::dropIfExists('guardian_codes');
    }

    public function down(): void
    {
        Schema::create('guardian_codes', function (Blueprint $table) {
            $table->foreignUuid('child_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at');
        });
        Schema::table('guardian_links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_by');
            $table->dropColumn('confirmed_at');
        });
        Schema::table('deck_shares', fn (Blueprint $table) => $table->dropConstrainedForeignId('shared_by'));
        Schema::table('decks', fn (Blueprint $table) => $table->dropColumn('share_review_pending'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_adult'));
    }
};
