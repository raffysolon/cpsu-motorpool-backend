<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\GpsLocation;
use App\Models\Trip;
use App\Services\GpsDistance;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

        $fresh = $captured === null || (
            $captured->gte(now('UTC')->subMinutes(5))
            && $captured->lte(now('UTC'))
        );

        $created = false;
        $conflict = false;

        $location = DB::transaction(function () use (
            $device,
            $validated,
            $recordId,
            $captured,
            $fresh,
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

        if (
            $created &&
            $fresh &&
            in_array($validated['upload_source'] ?? null, [null, 'live_lte'], true)
        ) {
            app(\App\Services\VehicleMovementMonitor::class)->observe($location);
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
                'destination' => $trip->destination,
                'active_movement_no' => $trip->movements->firstWhere('status', 'active')?->movement_no,
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

    public function syncLogs(Request $request)
    {
        $filters = $request->validate([
            'summary' => 'sometimes|boolean',
            'vehicle_id' => 'sometimes|integer|min:1',
            'device_registry_id' => 'sometimes|integer|min:1',
        ]);
        $query = GpsLocation::with('device:id,device_id');
        if (isset($filters['vehicle_id'])) {
            $vehicleId = $filters['vehicle_id'];
            $query->where(function ($scope) use ($vehicleId) {
                $scope->whereHas('device', fn ($device) => $device->where('vehicle_id', $vehicleId))
                    ->orWhere(fn ($legacy) => $legacy->whereNull('device_registry_id')->where('vehicle_id', $vehicleId));
            });
        }
        if (isset($filters['device_registry_id'])) {
            $query->where('device_registry_id', $filters['device_registry_id']);
        }
        if ($request->boolean('summary')) {
            $ranked = DB::table('gps_locations')->select('id')->selectRaw(
                'ROW_NUMBER() OVER (PARTITION BY device_registry_id, CASE WHEN device_registry_id IS NULL THEN vehicle_id END ORDER BY COALESCE(received_at, created_at, recorded_at) DESC, id DESC) AS upload_rank'
            );
            $query->whereIn('id', DB::query()->fromSub($ranked, 'uploads')->select('id')->where('upload_rank', 1));
        }
        $query->orderByRaw('COALESCE(received_at, created_at, recorded_at) DESC')->orderByDesc('id');
        if (!$request->boolean('summary')) {
            return response()->json($query->paginate(50));
        }

        $transfers = DB::table('gateway_sync_statuses')->get()->map(function ($status) {
            $status->reported_at = Carbon::parse($status->reported_at, 'UTC')->toISOString();
            return $status;
        });

        return response()->json(['data' => $query->get(), 'transfers' => $transfers]);
    }

    public function tripEta(Trip $trip)
    {
        $movement = $trip->movements()->where('status', 'active')->first();
        if ($trip->status !== 'active' || !$movement) {
            return response()->json(['status' => 'not_driving']);
        }
        if ((int) $movement->movement_no !== 1) {
            return response()->json(['status' => 'return_leg']);
        }
        if ($trip->destination_latitude === null || $trip->destination_longitude === null) {
            return response()->json(['status' => 'pin_missing']);
        }
        $gps = $trip->vehicle_id === null ? null : GpsLocation::query()
            ->where('vehicle_id', $trip->vehicle_id)
            ->orderByDesc('recorded_at')->orderByDesc('id')->first();
        if (!$gps) {
            return response()->json(['status' => 'gps_missing']);
        }
        $recordedAt = $gps->recorded_at;
        $age = $recordedAt->diffInSeconds(now(), false);
        if ($age < -5 || $age > 60) {
            return response()->json([
                'status' => 'gps_stale',
                'gps_recorded_at' => $recordedAt->toISOString(),
            ]);
        }

        $router = config('services.osrm.url');
        if (!$router) {
            return response()->json(['status' => 'routing_unconfigured']);
        }
        $coordinates = "{$gps->longitude},{$gps->latitude};{$trip->destination_longitude},{$trip->destination_latitude}";
        $key = 'trip-eta-route:' . hash('sha256', "$router|$coordinates");
        $route = Cache::remember($key, 60, function () use ($router, $coordinates) {
            try {
                $response = Http::acceptJson()
                    ->connectTimeout(3)->timeout(6)->withoutRedirecting()
                    ->get(rtrim($router, '/') . '/route/v1/driving/' . $coordinates, ['overview' => 'false']);
                $seconds = $response->json('routes.0.duration');
                if ($response->successful() && $response->json('code') === 'Ok'
                    && is_numeric($seconds) && is_finite((float) $seconds) && $seconds >= 0
                ) {
                    $meters = $response->json('routes.0.distance');
                    return [
                        'seconds' => (int) ceil($seconds),
                        'meters' => is_numeric($meters) && is_finite((float) $meters) && $meters >= 0 ? (float) $meters : null,
                    ];
                }
            } catch (ConnectionException) {
                return false;
            }

            return false;
        });
        if ($route === false) {
            return response()->json(['status' => 'routing_unavailable']);
        }

        $speed = $route['meters'] !== null ? $this->recentSpeedMetersPerSecond($gps) : null;
        $duration = $speed === null ? $route['seconds'] : (int) ceil($route['meters'] / $speed);

        return response()->json([
            'status' => 'available',
            'speed_kph' => $speed === null ? null : round($speed * 3.6, 1),
            'arrival_at' => $recordedAt->copy()->addSeconds($duration)->toISOString(),
            'gps_recorded_at' => $recordedAt->toISOString(),
        ]);
    }


    private function recentSpeedMetersPerSecond(GpsLocation $latest): ?float
    {
        $recordedAt = $latest->recorded_at;
        $previous = GpsLocation::query()
            ->where('vehicle_id', $latest->vehicle_id)
            ->where('device_registry_id', $latest->device_registry_id)
            ->whereBetween('recorded_at', [$recordedAt->copy()->subMinute(), $recordedAt->copy()->subSeconds(15)])
            ->orderBy('recorded_at')->orderBy('id')->first();
        if (!$previous) {
            return null;
        }

        $meters = GpsDistance::meters($previous->latitude, $previous->longitude, $latest->latitude, $latest->longitude);
        $speed = $meters / $previous->recorded_at->diffInSeconds($recordedAt);
        // Stationary readings and implausible GPS jumps use the router estimate.
        return $speed >= 5 / 3.6 && $speed <= 120 / 3.6 ? $speed : null;
    }

    public function recordedRoute(Trip $trip)
    {
        abort_unless($trip->status === 'completed', 404);

        if ($trip->vehicle_id === null) {
            return response()->json(['legs' => [], 'reason' => 'vehicle_missing']);
        }

        $legs = [];
        foreach ($trip->movements as $movement) {
            $departure = $movement->actual_departure_at;
            $arrival = $movement->actual_arrival_at;
            if (!$departure || !$arrival || $arrival->lt($departure)) {
                continue;
            }

            $locations = GpsLocation::query()
                ->where('vehicle_id', $trip->vehicle_id)
                ->whereBetween('recorded_at', [$departure->copy()->utc(), $arrival->copy()->utc()])
                ->orderBy('recorded_at')
                ->orderBy('id')
                ->get(['latitude', 'longitude', 'recorded_at']);

            $legs[] = [
                'movement_no' => $movement->movement_no,
                'points' => $locations->map(fn (GpsLocation $location) => [
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'recorded_at' => $location->recorded_at->toISOString(),
                ]),
            ];
        }

        return response()->json([
            'legs' => $legs,
            'reason' => $legs === [] ? 'actual_times_missing' : null,
        ]);
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
