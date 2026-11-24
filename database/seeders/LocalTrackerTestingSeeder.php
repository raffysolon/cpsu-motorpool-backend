<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class LocalTrackerTestingSeeder extends Seeder
{
    public function run(): void
    {
        $expectedDatabase = realpath(database_path()).DIRECTORY_SEPARATOR.'local-testing.sqlite';
        $configuredDatabase = (string) config('database.connections.sqlite.database');

        if (
            app()->environment() !== 'local-testing' ||
            config('database.default') !== 'sqlite' ||
            config('database.connections.sqlite.driver') !== 'sqlite' ||
            strtolower(str_replace('\\', '/', $configuredDatabase)) !==
                strtolower(str_replace('\\', '/', $expectedDatabase))
        ) {
            throw new RuntimeException(
                'LocalTrackerTestingSeeder may run only against the approved local SQLite database.'
            );
        }

        if (\PDO::getAvailableDrivers() !== ['sqlite']) {
            throw new RuntimeException(
                'LocalTrackerTestingSeeder requires the SQLite-only PHP runtime.'
            );
        }

        $adminEmail = (string) env('LOCAL_TEST_ADMIN_EMAIL');
        $adminPassword = (string) env('LOCAL_TEST_ADMIN_PASSWORD');
        $driverEmail = (string) env('LOCAL_TEST_DRIVER_EMAIL');
        $driverPassword = (string) env('LOCAL_TEST_DRIVER_PASSWORD');
        if (
            $adminEmail !== 'admin@local.test' ||
            $driverEmail !== 'driver@local.test' ||
            strlen($adminPassword) < 32 ||
            strlen($driverPassword) < 32 ||
            hash_equals($adminPassword, $driverPassword)
        ) {
            throw new RuntimeException('Separate local-only account credentials are required.');
        }

        $now = now();
        $utcNow = now('UTC');

        DB::transaction(function () use ($adminEmail, $adminPassword, $driverEmail, $driverPassword, $now, $utcNow): void {
            User::updateOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => 'Local Test Admin',
                    'password' => Hash::make($adminPassword),
                    'role' => 'admin',
                    'email_verified_at' => $now,
                ]
            );
            User::updateOrCreate(
                ['email' => $driverEmail],
                [
                    'name' => 'Local Test Driver',
                    'password' => Hash::make($driverPassword),
                    'role' => 'driver',
                    'email_verified_at' => $now,
                ]
            );

            $trackers = [
                [
                    'device_id' => 'LOCAL-ONLINE',
                    'name' => 'Local Test Vehicle 1',
                    'plate_no' => 'LOCAL-TEST-001',
                    'heartbeat_age' => 30,
                    'latitude' => 10.6713000,
                    'longitude' => 122.9511000,
                ],
                [
                    'device_id' => 'LOCAL-UNSTABLE',
                    'name' => 'Local Test Vehicle 2',
                    'plate_no' => 'LOCAL-TEST-002',
                    'heartbeat_age' => 90,
                    'latitude' => 10.6740000,
                    'longitude' => 122.9550000,
                ],
                [
                    'device_id' => 'LOCAL-UNREACHABLE',
                    'name' => 'Local Test Vehicle 3',
                    'plate_no' => 'LOCAL-TEST-003',
                    'heartbeat_age' => 180,
                    'latitude' => 10.6675000,
                    'longitude' => 122.9460000,
                ],
            ];

            foreach ($trackers as $tracker) {
                $vehicle = Vehicle::updateOrCreate(
                    ['plate_no' => $tracker['plate_no']],
                    ['name' => $tracker['name'], 'status' => 'active']
                );
                $existingDevice = DB::table('device_registry')
                    ->where('device_id', $tracker['device_id'])
                    ->first(['registered_at', 'created_at']);
                $registeredAt = $existingDevice === null ||
                    $existingDevice->registered_at === null
                        ? $now->format('Y-m-d H:i:s')
                        : $existingDevice->registered_at;
                $createdAt = $existingDevice === null ||
                    $existingDevice->created_at === null
                        ? $now->format('Y-m-d H:i:s')
                        : $existingDevice->created_at;
                $heartbeatAt = $utcNow->copy()->subSeconds($tracker['heartbeat_age']);

                DB::table('device_registry')->updateOrInsert(
                    ['device_id' => $tracker['device_id']],
                    [
                        'vehicle_id' => $vehicle->id,
                        'vehicle_plate' => $vehicle->plate_no,
                        'last_seen' => $now->format('Y-m-d H:i:s'),
                        'is_online' => true,
                        'registered_at' => $registeredAt,
                        'last_heartbeat_at' => $heartbeatAt->format('Y-m-d H:i:s'),
                        'updated_at' => $now->format('Y-m-d H:i:s'),
                        'created_at' => $createdAt,
                    ]
                );

                $deviceRegistryId = DB::table('device_registry')
                    ->where('device_id', $tracker['device_id'])
                    ->value('id');
                $recordId = $tracker['device_id'].'-1';
                DB::table('gps_locations')->updateOrInsert(
                    ['record_id' => $recordId],
                    [
                        'device_registry_id' => $deviceRegistryId,
                        'vehicle_id' => $vehicle->id,
                        'latitude' => $tracker['latitude'],
                        'longitude' => $tracker['longitude'],
                        'satellites' => 8,
                        'recorded_at' => $utcNow->copy()->subSeconds(5)->format('Y-m-d H:i:s'),
                        'record_id' => $recordId,
                        'device_sequence' => 1,
                        'received_at' => $utcNow->format('Y-m-d H:i:s'),
                        'upload_source' => 'live_lte',
                        'updated_at' => $utcNow->format('Y-m-d H:i:s'),
                        'created_at' => $utcNow->format('Y-m-d H:i:s'),
                    ]
                );
            }
        });

        $this->command?->info(
            'Local test accounts, three vehicles, GPS records, and heartbeat states are ready.'
        );
    }
}
