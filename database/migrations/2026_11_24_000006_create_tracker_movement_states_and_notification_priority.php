<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('priority', 20)->default('normal');
        });

        Schema::create('tracker_movement_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_registry_id')
                ->unique()
                ->constrained('device_registry')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('last_gps_location_id')->nullable();
            $table->dateTime('last_recorded_at')->nullable();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('last_satellites')->nullable();
            $table->dateTime('moving_since')->nullable();
            $table->unsignedBigInteger('movement_start_location_id')->nullable();
            $table->decimal('movement_start_latitude', 10, 7)->nullable();
            $table->decimal('movement_start_longitude', 10, 7)->nullable();
            $table->decimal('movement_distance_meters', 10, 2)->default(0);
            $table->boolean('movement_alerted')->default(false);
            $table->unsignedBigInteger('movement_context_trip_id')->nullable();
            $table->dateTime('stationary_since')->nullable();
            $table->decimal('stationary_start_latitude', 10, 7)->nullable();
            $table->decimal('stationary_start_longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('campus_tracking_trip_id')->nullable();
            $table->boolean('observed_outside_campus')->default(false);
            $table->unsignedBigInteger('return_reminder_trip_id')->nullable();
            $table->timestamps();

            $table->index('last_gps_location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_movement_states');

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
