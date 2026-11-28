<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_destinations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
        });
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE saved_destinations ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_destinations');
    }
};
