<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\GpsLocation;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GpsLocationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:100',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'satellites' => 'nullable|integer|min:0|max:255',
        ]);

        $device = Device::where(
            'device_id',
            $validated['device_id']
        )->first();

        if (!$device) {
            return response()->json([
                'message' => 'Device is not registered.',
            ], 404);
        }

        $location = DB::transaction(function () use (
            $device,
            $validated
        ) {
            $location = GpsLocation::create([
                'device_registry_id' => $device->id,
                'vehicle_id' => $device->vehicle_id,
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'satellites' => $validated['satellites'] ?? null,
                'recorded_at' => now(),
            ]);

            $device->update([
                'last_seen' => now(),
                'is_online' => true,
            ]);

            return $location;
        });

        return response()->json([
            'message' => 'GPS location saved successfully.',
            'location' => $location,
        ], 201);
    }

    public function latest(int $vehicle_id)
    {
        $location = GpsLocation::where(
            'vehicle_id',
            $vehicle_id
        )
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();

        if (!$location) {
            return response()->json([
                'message' => 'No GPS location found.',
            ], 404);
        }

        $trip = Trip::with('movements')
            ->where('vehicle_id', $vehicle_id)
            ->whereIn('status', ['approved', 'scheduled', 'active'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('scheduled_departure')
            ->first();
        $context = null;
        if ($trip) {
            $movement = $trip->movements->firstWhere('movement_no', 1);
            $scheduled = $movement?->scheduled_departure ?? Carbon::parse($trip->scheduled_departure);
            $context = [
                'id' => $trip->id,
                'status' => $trip->effective_status,
                'scheduled_departure' => $scheduled->toISOString(),
                'actual_departure_at' => $movement?->actual_departure_at?->toISOString(),
                'late_minutes' => $movement?->late_minutes,
                'departure_overdue' => in_array($trip->status, ['approved', 'scheduled'], true)
                    && !$movement?->actual_departure_at
                    && $scheduled->copy()->addMinutes(5)->lte(now()),
            ];
        }

        return response()->json(array_merge($location->toArray(), ['trip' => $context]));
    }

    public function syncLogs()
    {
        return response()->json(GpsLocation::with('device:id,device_id')
            ->orderByDesc('recorded_at')->orderByDesc('id')->paginate(50));
    }

    public function history(int $vehicle_id)
    {
        $locations = GpsLocation::where(
            'vehicle_id',
            $vehicle_id
        )
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate(100);

        return response()->json($locations);
    }
}
