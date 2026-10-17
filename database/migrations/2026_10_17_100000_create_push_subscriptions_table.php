<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily reminders (docs/specs/schreiben-und-erinnern.md, B):
 *
 * * push_subscriptions: devices that asked for reminders; one device
 *   (endpoint) belongs to one account.
 * * learning_settings.reminder_time: local time of the reminder, null = off.
 * * learning_settings.reminder_sent_on: the day the reminder was handled, so
 *   it goes out at most once a day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint')->unique();
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->timestampsTz();

            $table->index('user_id');
        });

        Schema::table('learning_settings', function (Blueprint $table) {
            $table->time('reminder_time')->nullable();
            $table->date('reminder_sent_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('learning_settings', function (Blueprint $table) {
            $table->dropColumn(['reminder_time', 'reminder_sent_on']);
        });

        Schema::dropIfExists('push_subscriptions');
    }
};
