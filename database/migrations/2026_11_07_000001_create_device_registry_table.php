<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_registry', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 100)->unique();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('vehicle_plate', 50);
            $table->timestamp('last_seen')->nullable();
            $table->boolean('is_online')->default(false);
            $table->timestamp('registered_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_registry');
    }
};
