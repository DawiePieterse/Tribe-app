<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two-way sync with a person's own calendar (Google, Outlook or iCloud): one connection per calendar,
 * and one link per event that exists on both sides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 12);
            $table->string('account')->nullable();
            // Tokens or the app-specific password, encrypted with the app key.
            $table->text('credentials');
            $table->string('calendar_id', 1000)->nullable();
            $table->string('calendar_name')->nullable();
            // Where events that come in from this calendar show in Tribe (null = whole family).
            $table->foreignId('household_id')->nullable()->constrained()->cascadeOnDelete();
            // Which Tribe events go out: everything the member sees, or only their own household's.
            $table->string('send', 10)->default('all');
            $table->timestamp('synced_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('event_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('calendar_connections')->cascadeOnDelete();
            // Null once the Tribe event is deleted, until the next sync deletes the other copy.
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            // Null when the person deleted Tribe's copy from their calendar: it isn't sent again.
            $table->string('remote_id', 500)->nullable();
            $table->string('remote_version')->nullable();
            $table->string('local_hash', 64)->nullable();
            // Where the event started: 'tribe' or 'remote'.
            $table->string('origin', 6);
            $table->timestamps();
            $table->unique(['connection_id', 'event_id']);
            $table->index(['connection_id', 'remote_id'], 'event_links_remote');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_links');
        Schema::dropIfExists('calendar_connections');
    }
};
