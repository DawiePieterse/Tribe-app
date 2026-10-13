<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Each person's favourite recipes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_favourites', function (Blueprint $table) {
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['recipe_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_favourites');
    }
};
