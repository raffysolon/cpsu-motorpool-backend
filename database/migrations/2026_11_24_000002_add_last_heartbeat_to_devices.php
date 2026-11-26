<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_registry', function (Blueprint $table) {
            // UTC device send time, used to reject replayed heartbeats.
            $table->timestamp('last_heartbeat_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('device_registry', function (Blueprint $table) {
            $table->dropColumn('last_heartbeat_at');
        });
    }
};
