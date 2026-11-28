<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gateway_sync_statuses', function (Blueprint $table) {
            $table->foreignId('device_registry_id')->primary()->constrained('device_registry')->cascadeOnDelete();
            $table->string('state', 32);
            $table->unsignedInteger('pending_count');
            $table->unsignedInteger('review_count');
            $table->dateTime('reported_at');
        });
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE gateway_sync_statuses ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_sync_statuses');
    }
};
