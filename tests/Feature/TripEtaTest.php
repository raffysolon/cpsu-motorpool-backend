<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\GpsLocation;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripEtaTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbound_eta_requires_a_pin_and_fresh_gps_and_uses_a_road_estimate(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-28T01:00:00Z'));
        config(['services.osrm.url' => 'https://routing.example.test']);
        Http::fake(['routing.example.test/*' => Http::response([
            'code' => 'Ok',
            'routes' => [['duration' => 900, 'distance' => 6000]],
        ])]);

        $driver = User::factory()->create(['role' => 'driver']);
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = Vehicle::create(['name' => 'Van', 'plate_no' => 'ETA1', 'status' => 'active']);
        $device = Device::create([
            'device_id' => 'ETA1', 'vehicle_id' => $vehicle->id, 'vehicle_plate' => 'ETA1',
        ]);
        $trip = Trip::create([
            'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
            'origin' => 'Campus A', 'destination' => 'Campus B', 'purpose' => 'Test',
            'status' => 'active', 'scheduled_departure' => '2026-11-28 08:00:00',
        ]);
        $movement = $trip->movements()->create([
            'movement_no' => 1, 'origin' => 'Campus A', 'destination' => 'Campus B',
            'actual_departure_at' => '2026-11-28 08:00:00', 'status' => 'active',
        ]);
        $url = "/api/trips/{$trip->id}/eta";

        Sanctum::actingAs($driver);
        $this->getJson($url)->assertForbidden();
        Sanctum::actingAs($admin);
        $this->getJson($url)->assertOk()->assertJsonPath('status', 'pin_missing');
        $this->putJson("/api/trips/{$trip->id}/destination-pin", [
            'latitude' => 10.2, 'longitude' => 123.3,
        ])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('status', 'gps_missing');

        GpsLocation::create([
            'device_registry_id' => $device->id, 'vehicle_id' => $vehicle->id,
            'recorded_at' => '2026-11-28 00:59:50', 'latitude' => 10.1, 'longitude' => 123.2,
        ]);
        $this->getJson($url)->assertOk()->assertJsonPath('status', 'available')
            ->assertJsonPath('arrival_at', '2026-11-28T01:14:50.000000Z');
        $this->getJson($url)->assertOk()->assertJsonPath('status', 'available');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/123.2,10.1;123.3,10.2'));

        GpsLocation::create([
            'device_registry_id' => $device->id, 'vehicle_id' => $vehicle->id,
            'recorded_at' => '2026-11-28 00:59:20', 'latitude' => 10.0973, 'longitude' => 123.2,
        ]);
        $this->getJson($url)->assertOk()->assertJsonPath('speed_kph', 36)
            ->assertJsonPath('arrival_at', '2026-11-28T01:09:50.000000Z');

        $this->travel(1)->minutes();
        $this->getJson($url)->assertOk()->assertJsonPath('status', 'gps_stale');
        $movement->update(['status' => 'completed']);
        $trip->movements()->create([
            'movement_no' => 2, 'origin' => 'Campus B', 'destination' => 'Campus A',
            'status' => 'active',
        ]);
        $this->getJson($url)->assertOk()->assertJsonPath('status', 'return_leg');
        Http::assertSentCount(1);
    }
}
