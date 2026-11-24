<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gps_locations', function (Blueprint $table) {
            $table->index(
                ['received_at', 'upload_source'],
                'gps_locations_received_source_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('gps_locations', function (Blueprint $table) {
            $table->dropIndex('gps_locations_received_source_index');
        });
    }
};
