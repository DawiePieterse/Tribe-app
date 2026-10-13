<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Helpers (a nanny, an au pair) who log in and see one household's days, and lifts: who takes a
 * child to an activity and who fetches them, per day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // 'there' (neem) or 'back' (haal).
            $table->string('direction', 5);
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'date', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifts');
    }
};
