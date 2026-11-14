<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\GpsLocation;
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

        return response()->json($location);
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
