<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Where each household lives, for its own weather. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            $table->string('town', 100)->nullable()->after('people');
            $table->decimal('lat', 8, 5)->nullable()->after('town');
            $table->decimal('lon', 8, 5)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('households', fn (Blueprint $table) => $table->dropColumn(['town', 'lat', 'lon']));
    }
};
