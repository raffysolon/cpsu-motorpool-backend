<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\GpsLocation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GpsLocationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:100',
            'record_id' => 'nullable|required_with:device_sequence|string|max:150|regex:/^[A-Za-z0-9_-]+$/',
            'device_sequence' => 'nullable|integer|min:1',
            'captured_at' => 'nullable|date',
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

        $recordId = $validated['record_id'] ?? null;

        if (
            $recordId !== null &&
            !str_starts_with($recordId, $validated['device_id'] . '-')
        ) {
            return response()->json([
                'message' => 'Record ID must belong to the submitted device.',
            ], 422);
        }

        // Convert incoming GPS capture timestamps to UTC.
        $captured = isset($validated['captured_at'])
            ? Carbon::parse($validated['captured_at'])->utc()
            : null;

        // Reject future timestamps while allowing historical offline GPS records.
        if (
            $captured &&
            $captured->greaterThan(now()->addMinutes(5))
        ) {
            return response()->json([
                'message' => 'GPS capture time is in the future.',
            ], 422);
        }

        $created = false;
        $conflict = false;

        $location = DB::transaction(function () use (
            $device,
            $validated,
            $recordId,
            $captured,
            &$created,
            &$conflict
        ) {
            // Generate a UTC timestamp for the current request.
            $utcNow = now('UTC')->format('Y-m-d H:i:s');

            if ($recordId !== null) {

                // Insert only if the unique record ID does not already exist.
                $inserted = GpsLocation::query()->insertOrIgnore([
                    'record_id' => $recordId,
                    'device_sequence' => $validated['device_sequence'] ?? null,
                    'device_registry_id' => $device->id,
                    'vehicle_id' => $device->vehicle_id,
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'satellites' => $validated['satellites'] ?? null,
                    'recorded_at' => $captured
                        ? $captured->format('Y-m-d H:i:s')
                        : $utcNow,
                    'received_at' => $utcNow,
                    'created_at' => $utcNow,
                    'updated_at' => $utcNow,
                ]);

                $location = GpsLocation::where(
                    'record_id',
                    $recordId
                )->firstOrFail();

                $created = $inserted === 1;

                // Compare saved and incoming data.
                // Interpret stored GPS timestamps as UTC.
                $conflict =
                    (int) $location->device_registry_id !== (int) $device->id
                    || abs(
                        (float) $location->latitude -
                        (float) $validated['latitude']
                    ) > 0.000001
                    || abs(
                        (float) $location->longitude -
                        (float) $validated['longitude']
                    ) > 0.000001
                    || (
                        $captured &&
                        Carbon::parse(
                            $location->getRawOriginal('recorded_at'),
                            'UTC'
                        )->timestamp !== $captured->timestamp
                    );

            } else {

                // Maintain compatibility with existing GPS firmware.
                $location = GpsLocation::create([
                    'device_registry_id' => $device->id,
                    'vehicle_id' => $device->vehicle_id,
                    'latitude' => $validated['latitude'],
                    'longitude' => $validated['longitude'],
                    'satellites' => $validated['satellites'] ?? null,
                    'recorded_at' => $captured
                        ? $captured->format('Y-m-d H:i:s')
                        : $utcNow,
                    'received_at' => $utcNow,
                    'created_at' => $utcNow,
                    'updated_at' => $utcNow,
                ]);

                $created = true;
            }

            if (!$conflict) {
                $device->update([
                    'last_seen' => now(),
                    'is_online' => true,
                ]);
            }

            return $location;
        });

        if ($conflict) {
            return response()->json([
                'message' => 'Record ID already exists with different GPS data.',
            ], 409);
        }

        return response()->json([
            'message' => 'GPS location saved successfully.',
            'acknowledged_record_id' => $recordId,
            'duplicate' => $recordId !== null && !$created,
            'location' => $location,
        ], $recordId !== null && !$created ? 200 : 201);
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

        return $location
            ? response()->json($location)
            : response()->json([
                'message' => 'No GPS location found.',
            ], 404);
    }

    public function history(int $vehicle_id)
    {
        return response()->json(
            GpsLocation::where('vehicle_id', $vehicle_id)
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->paginate(100)
        );
    }
}
