<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('action', 24);
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name');
            $table->string('actor_role');
            $table->dateTime('occurred_at');
            $table->unsignedInteger('late_minutes')->nullable();
            $table->unique(['trip_id', 'action']);
        });
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE trip_activities ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_activities');
    }
};
