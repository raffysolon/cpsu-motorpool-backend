<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
            $table->unsignedTinyInteger('movement_no');
            $table->string('origin');
            $table->string('destination');
            $table->dateTime('scheduled_departure')->nullable();
            $table->dateTime('actual_departure_at')->nullable();
            $table->dateTime('actual_arrival_at')->nullable();
            $table->decimal('departure_latitude', 10, 7)->nullable();
            $table->decimal('departure_longitude', 10, 7)->nullable();
            $table->decimal('arrival_latitude', 10, 7)->nullable();
            $table->decimal('arrival_longitude', 10, 7)->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamps();

            $table->unique(['trip_id', 'movement_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_movements');
    }
};
