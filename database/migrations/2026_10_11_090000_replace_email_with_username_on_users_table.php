<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts no longer have an email address: users sign in with a username,
 * and self-registered accounts wait for an administrator's approval.
 *
 * Existing accounts get a username derived from the local part of their
 * email and count as approved. The derivation lives here on purpose, not in
 * the model or UserService, so later changes there cannot alter what this
 * migration did.
 *
 * down() restores an empty, nullable email column — the old addresses are
 * gone for good.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->after('id');
            $table->timestampTz('approved_at')->nullable();
            // nullOnDelete: deleting the approving admin must not delete the
            // accounts they approved.
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
        });

        $this->backfill();

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 30)->nullable(false)->change();
            $table->unique('username');
            $table->dropUnique(['email']);
            $table->dropColumn('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->unique();
            $table->dropConstrainedForeignId('approved_by');
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'approved_at']);
        });
    }

    private function backfill(): void
    {
        $taken = [];

        $rows = DB::table('users')->orderBy('created_at')->get(['id', 'email', 'created_at']);
        foreach ($rows as $row) {
            $username = $this->freeUsername($this->baseFromEmail((string) $row->email), $taken);
            $taken[$username] = true;

            DB::table('users')->where('id', $row->id)->update([
                'username' => $username,
                'approved_at' => $row->created_at,
            ]);
        }
    }

    private function baseFromEmail(string $email): string
    {
        $local = mb_strtolower(trim(explode('@', $email)[0]));
        $local = strtr($local, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $local = (string) preg_replace('/[^a-z0-9._-]/', '', $local);
        $local = (string) preg_replace('/^[^a-z]+/', '', $local);

        return strlen($local) >= 3 ? substr($local, 0, 27) : 'user';
    }

    /** @param  array<string, true>  $taken */
    private function freeUsername(string $base, array $taken): string
    {
        if (! isset($taken[$base])) {
            return $base;
        }

        for ($n = 2; ; $n++) {
            if (! isset($taken[$base.$n])) {
                return $base.$n;
            }
        }
    }
};
