<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('users');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles');
            $table->string('origin');
            $table->string('destination');
            $table->string('purpose');
            $table->dateTime('scheduled_departure');
            $table->enum('status', ['pending', 'approved', 'active', 'completed', 'denied'])->default('pending');
            $table->decimal('total_distance', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
