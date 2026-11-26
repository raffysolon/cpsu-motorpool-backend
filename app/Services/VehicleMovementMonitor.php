<?php

namespace App\Services;

use App\Models\Device;
use App\Models\GpsLocation;
use App\Models\Notification;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VehicleMovementMonitor
{
    public function checkRecent(): int
    {
        $processed = 0;
        $cutoff = now('UTC')->subSeconds(
            config('tracking.movement.maximum_gps_age_seconds')
        );

        GpsLocation::query()
            ->whereNotNull('device_registry_id')
            ->whereNotNull('received_at')
            ->where('received_at', '>=', $cutoff->format('Y-m-d H:i:s'))
            ->where(function ($query) {
                $query->whereNull('upload_source')
                    ->orWhere('upload_source', 'live_lte');
            })
            ->orderBy('id')
            ->chunkById(100, function ($locations) use (&$processed) {
                foreach ($locations as $location) {
                    if ($this->observe($location)) {
                        $processed++;
                    }
                }
            });

        return $processed;
    }

    public function observe(GpsLocation $location): bool
    {
        if (
            $location->device_registry_id === null ||
            ! in_array($location->upload_source, [null, 'live_lte'], true)
        ) {
            return false;
        }

        $recordedValue = $location->getRawOriginal('recorded_at');
        $receivedValue = $location->getRawOriginal('received_at');
        if ($recordedValue === null || $receivedValue === null) {
            return false;
        }

        $recordedAt = Carbon::parse($recordedValue, 'UTC');
        $now = now('UTC');
        if (
            $recordedAt->lt($now->copy()->subSeconds(
                config('tracking.movement.maximum_gps_age_seconds')
            )) ||
            $recordedAt->gt($now->copy()->addSeconds(5))
        ) {
            return false;
        }

        $device = Device::find($location->device_registry_id);
        if (! $device) {
            return false;
        }

        $latitude = (float) $location->getRawOriginal('latitude');
        $longitude = (float) $location->getRawOriginal('longitude');
        $satellites = $location->getRawOriginal('satellites');
        $validFix = is_numeric($satellites)
            && (int) $satellites >= config('tracking.movement.minimum_satellites')
            && is_finite($latitude)
            && is_finite($longitude)
            && $latitude >= -90
            && $latitude <= 90
            && $longitude >= -180
            && $longitude <= 180;

        return DB::transaction(function () use (
            $device,
            $location,
            $recordedAt,
            $latitude,
            $longitude,
            $satellites,
            $validFix
        ): bool {
            $nowValue = now('UTC')->format('Y-m-d H:i:s');
            DB::table('tracker_movement_states')->insertOrIgnore([
                'device_registry_id' => $device->id,
                'created_at' => $nowValue,
                'updated_at' => $nowValue,
            ]);

            $state = DB::table('tracker_movement_states')
                ->where('device_registry_id', $device->id)
                ->lockForUpdate()
                ->first();

            if ($state->last_recorded_at !== null) {
                $previousAt = Carbon::parse($state->last_recorded_at, 'UTC');
                if (
                    $recordedAt->lt($previousAt) ||
                    ($recordedAt->equalTo($previousAt)
                        && $location->id <= (int) $state->last_gps_location_id)
                ) {
                    return false;
                }
            }

            $trip = $this->relevantTrip((int) $device->vehicle_id);
            $movingSince = $state->moving_since === null
                ? null
                : Carbon::parse($state->moving_since, 'UTC');
            $movementStartLocationId = $state->movement_start_location_id;
            $movementStartLatitude = $state->movement_start_latitude;
            $movementStartLongitude = $state->movement_start_longitude;
            $movementDistance = (float) $state->movement_distance_meters;
            $movementAlerted = (bool) $state->movement_alerted;
            $movementContextTripId = $state->movement_context_trip_id;
            $stationarySince = $state->stationary_since === null
                ? null
                : Carbon::parse($state->stationary_since, 'UTC');
            $stationaryStartLatitude = $state->stationary_start_latitude;
            $stationaryStartLongitude = $state->stationary_start_longitude;
            $campusTripId = $state->campus_tracking_trip_id;
            $observedOutsideCampus = (bool) $state->observed_outside_campus;
            $returnReminderTripId = $state->return_reminder_trip_id;
            $previousAt = $state->last_recorded_at === null
                ? null
                : Carbon::parse($state->last_recorded_at, 'UTC');
            $previousLatitude = $state->last_latitude === null
                ? null
                : (float) $state->last_latitude;
            $previousLongitude = $state->last_longitude === null
                ? null
                : (float) $state->last_longitude;
            $previousSatellites = $state->last_satellites;

            if (! $validFix || $previousAt === null) {
                $movingSince = null;
                $movementStartLocationId = null;
                $movementStartLatitude = null;
                $movementStartLongitude = null;
                $movementDistance = 0;
                $movementAlerted = false;
                $movementContextTripId = null;
                $stationarySince = null;
                $stationaryStartLatitude = null;
                $stationaryStartLongitude = null;
            } else {
                $elapsedSeconds = $recordedAt->getTimestamp() - $previousAt->getTimestamp();
                $sampleGap = $elapsedSeconds > 0
                    && $elapsedSeconds <= config('tracking.movement.maximum_sample_gap_seconds');
                $previousFix = is_numeric($previousSatellites)
                    && (int) $previousSatellites >= config('tracking.movement.minimum_satellites')
                    && $previousLatitude !== null
                    && $previousLongitude !== null;

                if (! $sampleGap || ! $previousFix) {
                    $movingSince = null;
                    $movementStartLocationId = null;
                    $movementStartLatitude = null;
                    $movementStartLongitude = null;
                    $movementDistance = 0;
                    $movementAlerted = false;
                    $movementContextTripId = null;
                    $stationarySince = null;
                    $stationaryStartLatitude = null;
                    $stationaryStartLongitude = null;
                } else {
                    $segmentDistance = $this->distanceMeters(
                        $previousLatitude,
                        $previousLongitude,
                        $latitude,
                        $longitude
                    );
                    $speedKph = ($segmentDistance / $elapsedSeconds) * 3.6;
                    $isMoving = $speedKph >= config('tracking.movement.minimum_speed_kph');

                    if ($isMoving) {
                        if ($movingSince === null) {
                            $movingSince = $previousAt;
                            $movementStartLocationId = $state->last_gps_location_id;
                            $movementStartLatitude = $previousLatitude;
                            $movementStartLongitude = $previousLongitude;
                            $movementDistance = $segmentDistance;
                            $movementAlerted = false;
                        } else {
                            $movementDistance += $segmentDistance;
                        }

                        $currentTripId = $trip?->id;
                        if ((int) $movementContextTripId !== (int) $currentTripId) {
                            $movementAlerted = false;
                            $movementContextTripId = $currentTripId;
                        }

                        $netDisplacement = $this->distanceMeters(
                            (float) $movementStartLatitude,
                            (float) $movementStartLongitude,
                            $latitude,
                            $longitude
                        );
                        $sustainedSeconds = $recordedAt->getTimestamp()
                            - $movingSince->getTimestamp();
                        if (
                            ! $movementAlerted &&
                            $sustainedSeconds >= config('tracking.movement.sustained_seconds') &&
                            $movementDistance >= config('tracking.movement.minimum_displacement_meters') &&
                            $netDisplacement >= config('tracking.movement.minimum_displacement_meters')
                        ) {
                            $this->notifyMovement(
                                $device,
                                $trip,
                                (int) $movementStartLocationId,
                                $netDisplacement,
                                $speedKph
                            );
                            $movementAlerted = true;
                        }

                        $stationarySince = null;
                        $stationaryStartLatitude = null;
                        $stationaryStartLongitude = null;
                    } else {
                        $movingSince = null;
                        $movementStartLocationId = null;
                        $movementStartLatitude = null;
                        $movementStartLongitude = null;
                        $movementDistance = 0;
                        $movementAlerted = false;
                        $movementContextTripId = null;

                        [
                            $stationarySince,
                            $stationaryStartLatitude,
                            $stationaryStartLongitude,
                            $campusTripId,
                            $observedOutsideCampus,
                            $returnReminderTripId,
                        ] = $this->checkCampusReturn(
                            $trip,
                            $recordedAt,
                            $previousAt,
                            $latitude,
                            $longitude,
                            $previousLatitude,
                            $previousLongitude,
                            $segmentDistance,
                            $speedKph,
                            $stationarySince,
                            $stationaryStartLatitude,
                            $stationaryStartLongitude,
                            $campusTripId,
                            $observedOutsideCampus,
                            $returnReminderTripId
                        );
                    }
                }
            }

            if ($trip?->status === 'active' && $this->campusCoordinates() !== null) {
                if ((int) $campusTripId !== (int) $trip->id) {
                    $campusTripId = $trip->id;
                    $observedOutsideCampus = false;
                    $stationarySince = null;
                    $stationaryStartLatitude = null;
                    $stationaryStartLongitude = null;
                    $returnReminderTripId = null;
                }
                if ($validFix && ! $this->isAtCampus($latitude, $longitude)) {
                    $observedOutsideCampus = true;
                }
            } else {
                $campusTripId = null;
                $observedOutsideCampus = false;
                $stationarySince = null;
                $stationaryStartLatitude = null;
                $stationaryStartLongitude = null;
                $returnReminderTripId = null;
            }

            DB::table('tracker_movement_states')
                ->where('id', $state->id)
                ->update([
                    'last_gps_location_id' => $location->id,
                    'last_recorded_at' => $recordedAt->format('Y-m-d H:i:s'),
                    'last_latitude' => $latitude,
                    'last_longitude' => $longitude,
                    'last_satellites' => $validFix ? (int) $satellites : null,
                    'moving_since' => $movingSince?->format('Y-m-d H:i:s'),
                    'movement_start_location_id' => $movementStartLocationId,
                    'movement_start_latitude' => $movementStartLatitude,
                    'movement_start_longitude' => $movementStartLongitude,
                    'movement_distance_meters' => $movementDistance,
                    'movement_alerted' => $movementAlerted,
                    'movement_context_trip_id' => $movementContextTripId,
                    'stationary_since' => $stationarySince?->format('Y-m-d H:i:s'),
                    'stationary_start_latitude' => $stationaryStartLatitude,
                    'stationary_start_longitude' => $stationaryStartLongitude,
                    'campus_tracking_trip_id' => $campusTripId,
                    'observed_outside_campus' => $observedOutsideCampus,
                    'return_reminder_trip_id' => $returnReminderTripId,
                    'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
                ]);

            return true;
        });
    }

    private function relevantTrip(int $vehicleId): ?Trip
    {
        return Trip::with('movements')
            ->where('vehicle_id', $vehicleId)
            ->where('status', 'active')
            ->latest('scheduled_departure')
            ->first()
            ?? Trip::with('movements')
                ->where('vehicle_id', $vehicleId)
                ->whereIn('status', ['approved', 'scheduled'])
                ->orderBy('scheduled_departure')
                ->first();
    }

    private function notifyMovement(
        Device $device,
        ?Trip $trip,
        int $incidentId,
        float $displacementMeters,
        float $speedKph
    ): void {
        $movement = $trip?->movements->firstWhere('movement_no', 1);
        $alreadyStarted = $trip?->status === 'active'
            || $movement?->actual_departure_at !== null;
        $approvedAndNotStarted = $trip !== null
            && in_array($trip->status, ['approved', 'scheduled'], true)
            && ! $alreadyStarted;

        if ($alreadyStarted) {
            return;
        }

        if ($approvedAndNotStarted) {
            $recipients = User::where('id', $trip->driver_id)
                ->orWhere('role', 'admin')
                ->get();
            $type = "pretrip:{$trip->id}";
            $message = "GPS confirms vehicle {$device->vehicle_plate} moved before trip #{$trip->id} was started.";
            $priority = 'normal';
            $tripId = $trip->id;
        } else {
            $recipients = User::where('role', 'admin')->get();
            $type = 'unauthorized';
            $message = "GPS confirms unassigned vehicle {$device->vehicle_plate} moved without an approved or active trip.";
            $priority = 'high';
            $tripId = null;
        }

        $message .= sprintf(
            ' Evidence: %.0f m displacement at %.1f km/h.',
            $displacementMeters,
            $speedKph
        );

        foreach ($recipients as $recipient) {
            Notification::firstOrCreate([
                'event_key' => "movement:{$type}:{$device->id}:{$incidentId}:{$recipient->id}",
            ], [
                'user_id' => $recipient->id,
                'trip_id' => $tripId,
                'message' => $message,
                'priority' => $priority,
                'is_read' => false,
            ]);
        }
    }

    private function checkCampusReturn(
        ?Trip $trip,
        Carbon $recordedAt,
        Carbon $previousAt,
        float $latitude,
        float $longitude,
        float $previousLatitude,
        float $previousLongitude,
        float $segmentDistance,
        float $speedKph,
        ?Carbon $stationarySince,
        mixed $stationaryStartLatitude,
        mixed $stationaryStartLongitude,
        mixed $campusTripId,
        bool $observedOutsideCampus,
        mixed $returnReminderTripId
    ): array {
        if (
            $trip?->status !== 'active' ||
            $this->campusCoordinates() === null
        ) {
            return [null, null, null, null, false, null];
        }

        if ((int) $campusTripId !== (int) $trip->id) {
            $campusTripId = $trip->id;
            $observedOutsideCampus = false;
            $stationarySince = null;
            $stationaryStartLatitude = null;
            $stationaryStartLongitude = null;
            $returnReminderTripId = null;
        }

        if (! $this->isAtCampus($latitude, $longitude)) {
            return [null, null, null, $campusTripId, true, $returnReminderTripId];
        }

        $isStationary = $speedKph < config('tracking.movement.minimum_speed_kph')
            && $segmentDistance <= config('tracking.movement.stationary_radius_meters');
        if (! $observedOutsideCampus || ! $isStationary) {
            return [null, null, null, $campusTripId, $observedOutsideCampus, $returnReminderTripId];
        }

        if ($stationarySince === null) {
            $stationarySince = $previousAt;
            $stationaryStartLatitude = $previousLatitude;
            $stationaryStartLongitude = $previousLongitude;
        }

        $stationaryDisplacement = $this->distanceMeters(
            (float) $stationaryStartLatitude,
            (float) $stationaryStartLongitude,
            $latitude,
            $longitude
        );
        if ($stationaryDisplacement > config('tracking.movement.stationary_radius_meters')) {
            $stationarySince = $recordedAt;
            $stationaryStartLatitude = $latitude;
            $stationaryStartLongitude = $longitude;
        }

        $stationarySeconds = $recordedAt->getTimestamp()
            - $stationarySince->getTimestamp();
        if (
            $stationarySeconds >= config('tracking.movement.sustained_seconds') &&
            (int) $returnReminderTripId !== (int) $trip->id
        ) {
            Notification::firstOrCreate([
                'event_key' => "trip-return-reminder:{$trip->id}:{$trip->driver_id}",
            ], [
                'user_id' => $trip->driver_id,
                'trip_id' => $trip->id,
                'message' => "The vehicle appears to have returned to campus and remained stationary. Please end trip #{$trip->id} when appropriate.",
                'priority' => 'normal',
                'is_read' => false,
            ]);
            $returnReminderTripId = $trip->id;
        }

        return [
            $stationarySince,
            $stationaryStartLatitude,
            $stationaryStartLongitude,
            $campusTripId,
            $observedOutsideCampus,
            $returnReminderTripId,
        ];
    }

    private function campusCoordinates(): ?array
    {
        $latitude = config('tracking.movement.campus_latitude');
        $longitude = config('tracking.movement.campus_longitude');
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;
        if (
            $latitude < -90 || $latitude > 90 ||
            $longitude < -180 || $longitude > 180
        ) {
            return null;
        }

        return [$latitude, $longitude];
    }

    private function isAtCampus(float $latitude, float $longitude): bool
    {
        $campus = $this->campusCoordinates();
        if ($campus === null) {
            return false;
        }

        return $this->distanceMeters(
            $latitude,
            $longitude,
            $campus[0],
            $campus[1]
        ) <= config('tracking.movement.campus_radius_meters');
    }

    private function distanceMeters(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        $earthRadiusMeters = 6371000;
        $latitudeDelta = deg2rad($latitude2 - $latitude1);
        $longitudeDelta = deg2rad($longitude2 - $longitude1);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitude1))
            * cos(deg2rad($latitude2))
            * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusMeters * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
