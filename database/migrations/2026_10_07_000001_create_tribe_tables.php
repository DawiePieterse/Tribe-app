<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tribe's own tables. A null household_id on events, lists and contacts means "the whole family";
 * a set one means "only that household", and Member::canSee() enforces it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('colour', 7);
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 10)->default('adult');
            $table->string('email')->nullable()->unique();
            $table->boolean('is_admin')->default(false);
            $table->date('birthday')->nullable();
            $table->string('colour', 7)->nullable();
            $table->boolean('large_text')->default(false);
            $table->string('clothes_size', 50)->nullable();
            $table->string('shoe_size', 50)->nullable();
            $table->text('favourites')->nullable();
            $table->text('allergies')->nullable();
            $table->text('wishlist')->nullable();
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('happened_on');
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('login_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
        });

        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->default('event');
            $table->string('title');
            $table->date('starts_on')->index();
            $table->date('ends_on')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('yearly')->default(false);
            $table->foreignId('host_household_id')->nullable()->constrained('households')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('gathering_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10);
            $table->unsignedTinyInteger('adults')->default(0);
            $table->unsignedTinyInteger('children')->default(0);
            $table->string('note')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'household_id']);
        });

        Schema::create('gathering_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 10)->default('shopping');
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->foreignId('assigned_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 20)->default('other');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['contacts', 'list_items', 'lists', 'gathering_items', 'gathering_responses', 'events',
            'invites', 'login_codes', 'milestones', 'members', 'households'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
