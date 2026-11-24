<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicates = DB::table('gps_locations')
            ->whereNotNull('device_sequence')
            ->groupBy('device_registry_id', 'device_sequence')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new \RuntimeException(
                'Duplicate device sequences detected. Review existing GPS records before migration.'
            );
        }

        Schema::table('gps_locations', function (Blueprint $table) {
            $table->unique(
                ['device_registry_id', 'device_sequence'],
                'gps_locations_device_sequence_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('gps_locations', function (Blueprint $table) {
            $table->dropUnique(
                'gps_locations_device_sequence_unique'
            );
        });
    }
};