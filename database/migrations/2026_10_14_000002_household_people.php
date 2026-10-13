<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** How many people a household cooks for, so meal ideas and their shopping lists fit the table. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            // Null: as many as there are members.
            $table->unsignedTinyInteger('people')->nullable()->after('colour');
        });
    }

    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn('people');
        });
    }
};
