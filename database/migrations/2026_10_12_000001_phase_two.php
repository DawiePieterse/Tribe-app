<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: repeating events, calendars subscribed by link, recipes and the week's meals, date polls
 * ("find a date") and the "Stuur na Tribe" inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 1000);
            $table->string('colour', 7)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->string('last_error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('repeats', 12)->default('none')->after('notes');
            $table->date('repeat_until')->nullable()->after('repeats');
            $table->foreignId('feed_id')->nullable()->after('created_by')->constrained('calendar_feeds')->cascadeOnDelete();
            $table->string('external_uid')->nullable()->after('feed_id');
            $table->unique(['feed_id', 'external_uid']);
        });

        DB::table('events')->where('yearly', true)->update(['repeats' => 'yearly']);

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('yearly');
        });

        Schema::table('members', function (Blueprint $table) {
            // The secret in this member's calendar link for the iPhone Calendar app (read-only feed).
            $table->string('calendar_token', 40)->nullable()->unique()->after('remember_token');
            // Hash of the key the "Stuur na Tribe" Shortcut sends; the key itself is shown once.
            $table->string('share_token_hash', 64)->nullable()->unique()->after('calendar_token');
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('servings', 50)->nullable();
            $table->string('time', 50)->nullable();
            $table->text('ingredients')->nullable();
            $table->text('method')->nullable();
            $table->text('notes')->nullable();
            $table->string('source_url', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->string('slot', 10)->default('dinner');
            $table->string('title')->nullable();
            $table->foreignId('recipe_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('date_polls', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('date_poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('date_poll_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('time')->nullable();
            $table->timestamps();
        });

        Schema::create('date_poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('date_poll_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('answer', 10);
            $table->foreignId('voted_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();
            $table->unique(['date_poll_option_id', 'household_id']);
        });

        Schema::create('inbox_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->json('suggestion')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_items');
        Schema::dropIfExists('date_poll_votes');
        Schema::dropIfExists('date_poll_options');
        Schema::dropIfExists('date_polls');
        Schema::dropIfExists('meals');
        Schema::dropIfExists('recipes');

        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['calendar_token']);
            $table->dropUnique(['share_token_hash']);
            $table->dropColumn(['calendar_token', 'share_token_hash']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->boolean('yearly')->default(false);
        });
        DB::table('events')->where('repeats', 'yearly')->update(['yearly' => true]);

        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['feed_id']);
            $table->dropUnique(['feed_id', 'external_uid']);
            $table->dropColumn(['feed_id', 'repeats', 'repeat_until', 'external_uid']);
        });

        Schema::dropIfExists('calendar_feeds');
    }
};
