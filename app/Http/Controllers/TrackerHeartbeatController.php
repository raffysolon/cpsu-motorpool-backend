<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrackerHeartbeatController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'device_id' => 'required|string|max:100',
            'sent_at' => ['required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/'],
        ]);
        $sent = Carbon::parse($data['sent_at'])->utc();
        $this->validateHeartbeatTime($sent, now('UTC'));

        return DB::transaction(function () use ($data, $sent) {
            $device = Device::where('device_id', $data['device_id'])->lockForUpdate()->first();
            if (!$device) {
                return response()->json(['message' => 'Device is not registered.'], 404);
            }

            $this->validateHeartbeatTime($sent, now('UTC'));

            $previous = $device->getRawOriginal('last_heartbeat_at');
            $ignored = $previous !== null && $sent->lte(Carbon::parse($previous, 'UTC'));
            if (!$ignored) {
                $device->last_heartbeat_at = $sent->format('Y-m-d H:i:s');
                $device->last_heartbeat_received_at = now('UTC')->format('Y-m-d H:i:s');
                $device->last_seen = now();
                $device->is_online = true;
                $device->save();
            }

            return response()->json([
                'message' => $ignored ? 'Heartbeat already acknowledged or superseded.' : 'Heartbeat acknowledged.',
                'device_id' => $device->device_id,
                'acknowledged_sent_at' => $sent->toISOString(),
                'ignored' => $ignored,
                'is_online' => $device->is_online,
                'last_heartbeat_received_at' => $device->getRawOriginal('last_heartbeat_received_at'),
                'availability_status' => $device->availability_status,
            ]);
        });
    }

    private function validateHeartbeatTime(Carbon $sent, Carbon $now): void
    {
        if (
            $sent->lt($now->copy()->subSeconds(
                config('tracking.heartbeat_max_age_seconds')
            )) ||
            $sent->gt($now->copy()->addSeconds(
                config('tracking.heartbeat_future_tolerance_seconds')
            ))
        ) {
            throw ValidationException::withMessages([
                'sent_at' => 'Heartbeat time is stale or too far in the future.',
            ]);
        }
    }
}
