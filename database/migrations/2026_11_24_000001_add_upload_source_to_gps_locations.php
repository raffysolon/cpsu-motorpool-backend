<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gps_locations', function (Blueprint $table) {
            // Null means unknown/legacy; never guess a historical transport.
            $table->string('upload_source', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('gps_locations', function (Blueprint $table) {
            $table->dropColumn('upload_source');
        });
    }
};
