<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Notification;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TripDepartureMonitor;
use App\Services\VehicleMovementMonitor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LocalMonitoringScenariosSeeder extends Seeder
{
    public function run(): void
    {
        app(LocalTrackerTestingSeeder::class)->run();
        $this->assertIsolatedDatabase();

        $driver = User::where('email', 'driver@local.test')->firstOrFail();
        $admin = User::where('email', 'admin@local.test')->firstOrFail();
        $now = now();
        $utcNow = now('UTC');
        $fixtures = [
            [
                'device_id' => 'LOCAL-SCENARIO-PRETRIP',
                'name' => 'Local Approved Pre-trip Scenario',
                'plate_no' => 'LOCAL-PRE-001',
                'heartbeat_age' => 30,
                'trip' => 'approved',
            ],
            [
                'device_id' => 'LOCAL-SCENARIO-UNAUTHORIZED',
                'name' => 'Local Unauthorized Movement Scenario',
                'plate_no' => 'LOCAL-UNAUTH-001',
                'heartbeat_age' => 30,
                'trip' => null,
            ],
            [
                'device_id' => 'LOCAL-SCENARIO-ACTIVE',
                'name' => 'Local Active Trip Scenario',
                'plate_no' => 'LOCAL-ACTIVE-001',
                'heartbeat_age' => 30,
                'trip' => 'active',
            ],
            [
                'device_id' => 'LOCAL-SCENARIO-NEAR-DEPARTURE',
                'name' => 'Local Unreachable Departure Scenario',
                'plate_no' => 'LOCAL-NEAR-001',
                'heartbeat_age' => 180,
                'trip' => 'near',
            ],
            [
                'device_id' => 'LOCAL-SCENARIO-OVERDUE',
                'name' => 'Local Overdue Departure Scenario',
                'plate_no' => 'LOCAL-OVERDUE-001',
                'heartbeat_age' => 30,
                'trip' => 'overdue',
            ],
            [
                'device_id' => 'LOCAL-SCENARIO-HISTORICAL',
                'name' => 'Local Recovered and Stale GPS Scenario',
                'plate_no' => 'LOCAL-HISTORY-001',
                'heartbeat_age' => 30,
                'trip' => null,
            ],
        ];

        DB::transaction(function () use ($fixtures, $driver, $now, $utcNow): void {
            $deviceIds = [];
            foreach ($fixtures as $fixture) {
                [$vehicle, $deviceRegistryId] = $this->upsertDevice(
                    $fixture,
                    $now,
                    $utcNow
                );
                $deviceIds[] = $deviceRegistryId;

                if ($fixture['trip'] === 'approved') {
                    $trip = $this->upsertTrip(
                        $driver,
                        $vehicle,
                        'Local scenario: approved trip not started',
                        $now->copy()->addHour(),
                        'approved'
                    );
                    $this->upsertMovement($trip, $trip->scheduled_departure, 'scheduled');
                    $this->upsertMovementGps($fixture, $vehicle, $deviceRegistryId, $utcNow);
                } elseif ($fixture['trip'] === 'active') {
                    $trip = $this->upsertTrip(
                        $driver,
                        $vehicle,
                        'Local scenario: authorized active trip',
                        $now->copy()->subMinute(),
                        'active'
                    );
                    $this->upsertMovement(
                        $trip,
                        $trip->scheduled_departure,
                        'active',
                        $now->copy()->subMinute()
                    );
                    $this->upsertMovementGps($fixture, $vehicle, $deviceRegistryId, $utcNow);
                } elseif ($fixture['trip'] === 'near') {
                    $trip = $this->upsertTrip(
                        $driver,
                        $vehicle,
                        'Local scenario: unreachable near departure',
                        $now->copy()->addMinutes(5),
                        'approved'
                    );
                    $this->upsertMovement($trip, $trip->scheduled_departure, 'scheduled');
                    $this->upsertSingleGpsFix(
                        $fixture,
                        $vehicle,
                        $deviceRegistryId,
                        $utcNow
                    );
                } elseif ($fixture['trip'] === 'overdue') {
                    $trip = $this->upsertTrip(
                        $driver,
                        $vehicle,
                        'Local scenario: departure overdue',
                        $now->copy()->subMinutes(6),
                        'approved'
                    );
                    $this->upsertMovement($trip, $trip->scheduled_departure, 'scheduled');
                    $this->upsertSingleGpsFix(
                        $fixture,
                        $vehicle,
                        $deviceRegistryId,
                        $utcNow
                    );
                } elseif ($fixture['trip'] === null &&
                    $fixture['device_id'] !== 'LOCAL-SCENARIO-HISTORICAL') {
                    $this->upsertMovementGps($fixture, $vehicle, $deviceRegistryId, $utcNow);
                } else {
                    $this->upsertHistoricalGps($fixture, $vehicle, $deviceRegistryId, $utcNow);
                }
            }

            DB::table('tracker_movement_states')
                ->whereIn('device_registry_id', $deviceIds)
                ->delete();
        });

        $movementMonitor = app(VehicleMovementMonitor::class);
        $movementMonitor->checkRecent();
        $movementCount = Notification::count();
        $movementMonitor->checkRecent();
        if (Notification::count() !== $movementCount) {
            throw new RuntimeException(
                'Repeated movement monitoring created duplicate local notifications.'
            );
        }

        $departureMonitor = app(TripDepartureMonitor::class);
        $departureMonitor->check();
        $departureCount = Notification::count();
        $departureMonitor->check();
        if (Notification::count() !== $departureCount) {
            throw new RuntimeException(
                'Repeated departure monitoring created duplicate local notifications.'
            );
        }

        $this->assertScenarioNotifications($admin, $driver);
        $this->command?->info(
            'Local monitoring scenarios are ready; repeated monitoring remained idempotent.'
        );
    }

    private function assertIsolatedDatabase(): void
    {
        $expectedDatabase = realpath(database_path()).DIRECTORY_SEPARATOR.'local-testing.sqlite';
        $configuredDatabase = (string) config('database.connections.sqlite.database');

        if (
            app()->environment() !== 'local-testing' ||
            config('database.default') !== 'sqlite' ||
            config('database.connections.sqlite.driver') !== 'sqlite' ||
            strtolower(str_replace('\\', '/', $configuredDatabase)) !==
                strtolower(str_replace('\\', '/', $expectedDatabase)) ||
            \PDO::getAvailableDrivers() !== ['sqlite']
        ) {
            throw new RuntimeException(
                'Local monitoring scenarios require the approved SQLite-only local environment.'
            );
        }
    }

    private function upsertDevice(array $fixture, $now, $utcNow): array
    {
        $vehicle = Vehicle::updateOrCreate(
            ['plate_no' => $fixture['plate_no']],
            ['name' => $fixture['name'], 'status' => 'active']
        );
        $existing = DB::table('device_registry')
            ->where('device_id', $fixture['device_id'])
            ->first(['registered_at', 'created_at']);
        $registeredAt = $existing->registered_at ?? $now->format('Y-m-d H:i:s');
        $createdAt = $existing->created_at ?? $now->format('Y-m-d H:i:s');
        $heartbeatAt = $utcNow->copy()->subSeconds($fixture['heartbeat_age']);
        $lastSeenAt = $now->copy()->subSeconds($fixture['heartbeat_age']);

        DB::table('device_registry')->updateOrInsert(
            ['device_id' => $fixture['device_id']],
            [
                'vehicle_id' => $vehicle->id,
                'vehicle_plate' => $vehicle->plate_no,
                'last_seen' => $lastSeenAt->format('Y-m-d H:i:s'),
                'is_online' => $fixture['heartbeat_age'] < 300,
                'registered_at' => $registeredAt,
                'last_heartbeat_at' => $heartbeatAt->format('Y-m-d H:i:s'),
                'last_heartbeat_received_at' => $heartbeatAt->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
                'created_at' => $createdAt,
            ]
        );

        $deviceRegistryId = (int) DB::table('device_registry')
            ->where('device_id', $fixture['device_id'])
            ->value('id');

        return [$vehicle, $deviceRegistryId];
    }

    private function upsertTrip(
        User $driver,
        Vehicle $vehicle,
        string $purpose,
        $proposedDeparture,
        string $status
    ): Trip {
        $trip = Trip::firstOrNew([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'purpose' => $purpose,
        ]);
        if (! $trip->exists) {
            $trip->origin = 'CPSU Campus';
            $trip->destination = 'Local Test Route';
            $trip->scheduled_departure = $proposedDeparture;
            $trip->source = 'local-testing';
        }
        $trip->status = $status;
        $trip->save();

        return $trip;
    }

    private function upsertMovement(
        Trip $trip,
        string|\DateTimeInterface $scheduledDeparture,
        string $status,
        $actualDeparture = null
    ): void {
        $trip->movements()->updateOrCreate(
            ['movement_no' => 1],
            [
                'origin' => $trip->origin,
                'destination' => $trip->destination,
                'scheduled_departure' => $scheduledDeparture,
                'actual_departure_at' => $actualDeparture,
                'actual_arrival_at' => null,
                'status' => $status,
            ]
        );
    }

    private function upsertMovementGps(
        array $fixture,
        Vehicle $vehicle,
        int $deviceRegistryId,
        $utcNow
    ): void {
        for ($step = 0; $step < 4; $step++) {
            $recordedAt = $utcNow->copy()->subSeconds(45 - ($step * 15));
            $this->upsertGps(
                $fixture,
                $vehicle,
                $deviceRegistryId,
                $recordedAt,
                10.6713,
                122.9511 + ($step * 0.00023),
                $step + 1,
                'live_lte',
                'MOVE'
            );
        }
    }

    private function upsertSingleGpsFix(
        array $fixture,
        Vehicle $vehicle,
        int $deviceRegistryId,
        $utcNow
    ): void {
        $this->upsertGps(
            $fixture,
            $vehicle,
            $deviceRegistryId,
            $utcNow->copy()->subSeconds(5),
            10.6713,
            122.9511,
            1,
            'live_lte',
            'GPS'
        );
    }

    private function upsertHistoricalGps(
        array $fixture,
        Vehicle $vehicle,
        int $deviceRegistryId,
        $utcNow
    ): void {
        for ($step = 0; $step < 4; $step++) {
            $this->upsertGps(
                $fixture,
                $vehicle,
                $deviceRegistryId,
                $utcNow->copy()->subSeconds(45 - ($step * 15)),
                10.6713,
                122.9511 + ($step * 0.00023),
                $step + 1,
                'recovered_lora',
                'RECOVERED'
            );
        }
        for ($step = 0; $step < 4; $step++) {
            $this->upsertGps(
                $fixture,
                $vehicle,
                $deviceRegistryId,
                $utcNow->copy()->subMinutes(5)->addSeconds($step * 15),
                10.6713,
                122.9511 + ($step * 0.00023),
                $step + 5,
                'live_lte',
                'STALE'
            );
        }
    }

    private function upsertGps(
        array $fixture,
        Vehicle $vehicle,
        int $deviceRegistryId,
        $recordedAt,
        float $latitude,
        float $longitude,
        int $sequence,
        string $source,
        string $recordGroup
    ): void {
        $recordId = $fixture['device_id'].'-'.$recordGroup.'-'.$sequence;
        $receivedAt = now('UTC');
        DB::table('gps_locations')->updateOrInsert(
            ['record_id' => $recordId],
            [
                'device_registry_id' => $deviceRegistryId,
                'vehicle_id' => $vehicle->id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'satellites' => 8,
                'recorded_at' => $recordedAt->format('Y-m-d H:i:s'),
                'record_id' => $recordId,
                'device_sequence' => $sequence,
                'received_at' => $receivedAt->format('Y-m-d H:i:s'),
                'upload_source' => $source,
                'updated_at' => $receivedAt->format('Y-m-d H:i:s'),
                'created_at' => $receivedAt->format('Y-m-d H:i:s'),
            ]
        );
    }

    private function assertScenarioNotifications(User $admin, User $driver): void
    {
        $preTrip = Trip::where('purpose', 'Local scenario: approved trip not started')
            ->firstOrFail();
        $unauthorizedDevice = Device::where(
            'device_id',
            'LOCAL-SCENARIO-UNAUTHORIZED'
        )->firstOrFail();
        $activeDevice = Device::where(
            'device_id',
            'LOCAL-SCENARIO-ACTIVE'
        )->firstOrFail();
        $historyDevice = Device::where(
            'device_id',
            'LOCAL-SCENARIO-HISTORICAL'
        )->firstOrFail();
        $activeTrip = Trip::where('purpose', 'Local scenario: authorized active trip')
            ->firstOrFail();
        $nearTrip = Trip::where('purpose', 'Local scenario: unreachable near departure')
            ->firstOrFail();
        $overdueTrip = Trip::where('purpose', 'Local scenario: departure overdue')
            ->firstOrFail();
        $preTripMovement = $preTrip->movements()
            ->where('movement_no', 1)
            ->first();

        $this->assertRecipients(
            "movement:pretrip:{$preTrip->id}:",
            [$admin->id, $driver->id],
            'normal'
        );
        if (
            $preTrip->status !== 'approved' ||
            $preTripMovement?->status !== 'scheduled' ||
            $preTripMovement?->actual_departure_at !== null ||
            $activeTrip->status !== 'active'
        ) {
            throw new RuntimeException(
                'GPS movement changed a synthetic trip lifecycle state.'
            );
        }
        $this->assertRecipients(
            "movement:unauthorized:{$unauthorizedDevice->id}:",
            [$admin->id],
            'high'
        );
        $this->assertNoMovementAlert($activeDevice->id);
        $this->assertRecipients(
            "tracker-departure-unreachable:{$nearTrip->id}:",
            [$admin->id, $driver->id]
        );
        $this->assertRecipients(
            "departure-overdue:{$overdueTrip->id}:",
            [$admin->id, $driver->id]
        );
        $this->assertNoMovementAlert($historyDevice->id);
    }

    private function assertRecipients(
        string $eventPrefix,
        array $recipientIds,
        ?string $priority = null
    ): void {
        $notifications = Notification::where(
            'event_key',
            'like',
            $eventPrefix.'%'
        )->get();
        $actualRecipients = $notifications->pluck('user_id')->all();
        sort($recipientIds);
        sort($actualRecipients);
        if (
            $actualRecipients !== $recipientIds ||
            ($priority !== null &&
                $notifications->contains(
                    fn (Notification $notification) => $notification->priority !== $priority
                ))
        ) {
            throw new RuntimeException(
                "Local scenario notifications did not match expected recipients for {$eventPrefix}."
            );
        }
    }

    private function assertNoMovementAlert(int $deviceId): void
    {
        if (
            Notification::where('event_key', 'like', "movement:%:{$deviceId}:%")
                ->exists()
        ) {
            throw new RuntimeException(
                "A current movement alert was created for local device {$deviceId}."
            );
        }
    }
}
