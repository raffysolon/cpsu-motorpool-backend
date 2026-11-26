<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_registry', function (Blueprint $table) {
            $table->timestamp('last_heartbeat_received_at')->nullable();
        });

        $migrationTime = Carbon::now('UTC');
        DB::table('device_registry')
            ->whereNotNull('last_heartbeat_at')
            ->select(['id', 'last_heartbeat_at'])
            ->orderBy('id')
            ->chunkById(100, function ($devices) use ($migrationTime): void {
                foreach ($devices as $device) {
                    $sentAt = Carbon::parse($device->last_heartbeat_at, 'UTC');
                    DB::table('device_registry')
                        ->where('id', $device->id)
                        ->update([
                            'last_heartbeat_received_at' => $sentAt->gt($migrationTime)
                                ? $migrationTime->format('Y-m-d H:i:s')
                                : $sentAt->format('Y-m-d H:i:s'),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('device_registry', function (Blueprint $table) {
            $table->dropColumn('last_heartbeat_received_at');
        });
    }
};
