<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who usually takes and fetches (on the event; a Lift row is then only an exception for one day),
 * days a repeating entry falls away, and the evening summary by email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('there_member_id')->nullable()->after('member_id')->constrained('members')->nullOnDelete();
            $table->foreignId('back_member_id')->nullable()->after('there_member_id')->constrained('members')->nullOnDelete();
        });

        Schema::create('event_skips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();
            $table->unique(['event_id', 'date']);
        });

        Schema::table('members', function (Blueprint $table) {
            $table->boolean('evening_email')->default(false)->after('large_text');
        });
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('evening_email'));
        Schema::dropIfExists('event_skips');
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('there_member_id');
            $table->dropConstrainedForeignId('back_member_id');
        });
    }
};
