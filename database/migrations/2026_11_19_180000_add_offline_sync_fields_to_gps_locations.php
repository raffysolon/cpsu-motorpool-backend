<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gps_locations', function (Blueprint $table) {
            // Nullable for the existing legacy GPS rows and older firmware.
            $table->string('record_id', 150)->nullable();
            $table->unsignedBigInteger('device_sequence')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->unique('record_id', 'gps_locations_record_id_unique');
            $table->index(['device_registry_id', 'device_sequence'], 'gps_locations_device_sequence_index');
        });
    }

    public function down(): void
    {
        Schema::table('gps_locations', function (Blueprint $table) {
            $table->dropUnique('gps_locations_record_id_unique');
            $table->dropIndex('gps_locations_device_sequence_index');
            $table->dropColumn(['record_id', 'device_sequence', 'received_at']);
        });
    }
};
