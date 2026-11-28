<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\GpsLocation;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecordedTripRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_only_the_completed_trips_gps_points_in_capture_order(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = Vehicle::create(['name' => 'Van', 'plate_no' => 'ABC1', 'status' => 'active']);
        $otherVehicle = Vehicle::create(['name' => 'Car', 'plate_no' => 'ABC2', 'status' => 'active']);
        $trip = Trip::create([
            'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
            'origin' => 'Campus A', 'destination' => 'Campus B', 'purpose' => 'Test',
            'status' => 'completed', 'scheduled_departure' => '2026-11-28 08:00:00',
        ]);
        $url = "/api/trips/{$trip->id}/recorded-route";
        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs($driver);
        $this->getJson($url)->assertForbidden();
        Sanctum::actingAs($admin);
        $this->getJson($url)->assertOk()->assertJsonPath('reason', 'actual_times_missing');

        foreach ([1 => [8, 9], 2 => [11, 12]] as $leg => [$start, $end]) {
            $trip->movements()->create([
                'movement_no' => $leg, 'origin' => 'Campus A', 'destination' => 'Campus B',
                'actual_departure_at' => sprintf('2026-11-28 %02d:00:00', $start),
                'actual_arrival_at' => sprintf('2026-11-28 %02d:00:00', $end),
                'status' => 'completed',
            ]);
        }

        // Movement times use Philippine time. The tracker stores GPS times in UTC.
        foreach ([
            [$vehicle, '00:30:00', 10.4],
            [$vehicle, '00:10:00', 10.2],
            [$otherVehicle, '00:20:00', 13.0],
            [$vehicle, '02:00:00', 10.5],
            [$vehicle, '03:15:00', 10.6],
            [$vehicle, '04:01:00', 10.7],
        ] as [$owner, $time, $latitude]) {
            $device = Device::firstOrCreate(['device_id' => $owner->plate_no], [
                'vehicle_id' => $owner->id, 'vehicle_plate' => $owner->plate_no,
            ]);
            GpsLocation::create([
                'device_registry_id' => $device->id,
                'vehicle_id' => $owner->id, 'recorded_at' => "2026-11-28 $time",
                'received_at' => '2026-11-29 06:00:00', 'latitude' => $latitude, 'longitude' => 123.4,
            ]);
        }

        $this->getJson($url)->assertOk()
            ->assertJsonCount(2, 'legs')
            ->assertJsonCount(2, 'legs.0.points')
            ->assertJsonPath('legs.0.points.0.latitude', 10.2)
            ->assertJsonPath('legs.0.points.1.latitude', 10.4)
            ->assertJsonPath('legs.1.movement_no', 2)
            ->assertJsonCount(1, 'legs.1.points')
            ->assertJsonPath('legs.1.points.0.latitude', 10.6);
    }
}
