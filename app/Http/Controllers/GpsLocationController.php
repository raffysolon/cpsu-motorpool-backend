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
            'record_id' => 'nullable|required_with:device_sequence|required_if:upload_source,recovered_lte,recovered_lora|string|max:150|regex:/^[A-Za-z0-9_-]+$/',
            'device_sequence' => 'nullable|required_if:upload_source,recovered_lte,recovered_lora|integer|min:1|max:9223372036854775807',
            'captured_at' => 'nullable|required_with:upload_source|date',
            'upload_source' => 'nullable|in:live_lte,recovered_lte,recovered_lora',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'satellites' => 'nullable|integer|min:0|max:255',
        ]);

        // Match database coordinate precision before insertion.
        foreach (['latitude', 'longitude'] as $coordinate) {
            $validated[$coordinate] = round(
                (float) $validated[$coordinate],
                7
            );
        }

        foreach (['device_sequence', 'satellites'] as $integer) {
            if (isset($validated[$integer])) {
                $validated[$integer] = (int) $validated[$integer];
            }
        }

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

        // Preserve the original GPS capture timestamp in UTC.
        $captured = isset($validated['captured_at'])
            ? Carbon::parse($validated['captured_at'])->utc()
            : null;

        // Allow historical records but reject invalid future timestamps.
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
            $utcNow = now('UTC')->format('Y-m-d H:i:s');

            if ($recordId !== null) {

                // Database uniqueness protects both record IDs
                // and device sequences against concurrent uploads.
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
                    'upload_source' => $validated['upload_source'] ?? null,
                    'created_at' => $utcNow,
                    'updated_at' => $utcNow,
                ]);

                $location = GpsLocation::where(
                    'record_id',
                    $recordId
                )->first();

                // An ignored insert without a matching record ID
                // indicates a conflicting unique key.
                if ($location === null) {
                    $conflict = true;

                    return null;
                }

                $created = $inserted === 1;

                // Verify that a retransmission contains the same GPS data.
                $conflict =
                    (int) $location->device_registry_id !== (int) $device->id
                    || round((float) $location->latitude, 7) !== $validated['latitude']
                    || round((float) $location->longitude, 7) !== $validated['longitude']
                    || $location->device_sequence !== ($validated['device_sequence'] ?? null)
                    || $location->satellites !== ($validated['satellites'] ?? null)
                    || (
                        $captured &&
                        Carbon::parse(
                            $location->getRawOriginal('recorded_at'),
                            'UTC'
                        )->timestamp !== $captured->timestamp
                    );

            } else {

                // Preserve compatibility with existing GPS firmware.
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
                    'upload_source' => $validated['upload_source'] ?? null,
                    'created_at' => $utcNow,
                    'updated_at' => $utcNow,
                ]);

                $created = true;
            }

            // Recovered records and duplicate uploads must not
            // incorrectly indicate current tracker connectivity.
            $source = $validated['upload_source'] ?? null;

            $fresh = $captured === null || (
                $captured->gte(now('UTC')->subMinutes(5))
                && $captured->lte(now('UTC'))
            );

            if (
                !$conflict &&
                $created &&
                $fresh &&
                ($source === null || $source === 'live_lte')
            ) {
                $device->update([
                    'last_seen' => now(),
                    'is_online' => true,
                ]);
            }

            return $location;
        });

        if ($conflict) {
            return response()->json([
                'message' => 'Record ID or device sequence already exists with conflicting GPS data.',
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
        $location = GpsLocation::where('vehicle_id', $vehicle_id)
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

            $scheduled = $movement?->scheduled_departure
                ?? Carbon::parse($trip->scheduled_departure);

            $context = [
                'id' => $trip->id,
                'status' => $trip->effective_status,
                'scheduled_departure' => $scheduled->toISOString(),
                'actual_departure_at' => $movement?->actual_departure_at?->toISOString(),
                'late_minutes' => $movement?->late_minutes,
                'departure_overdue' => in_array(
                    $trip->status,
                    ['approved', 'scheduled'],
                    true
                )
                    && !$movement?->actual_departure_at
                    && $scheduled->copy()->addMinutes(5)->lte(now()),
            ];
        }

        return response()->json(
            array_merge($location->toArray(), ['trip' => $context])
        );
    }

    public function syncLogs()
    {
        return response()->json(
            GpsLocation::with('device:id,device_id')
                ->orderByDesc('recorded_at')
                ->orderByDesc('id')
                ->paginate(50)
        );
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
