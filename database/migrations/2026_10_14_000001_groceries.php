<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What a household bought recently (read off a till slip or Sixty60 order), for meal ideas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groceries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->date('bought_on');
            $table->foreignId('created_by')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();
            $table->unique(['household_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groceries');
    }
};
