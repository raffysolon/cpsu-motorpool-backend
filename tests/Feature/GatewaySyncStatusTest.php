<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GatewaySyncStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_reports_are_visible_to_admins_before_any_gps_upload(): void
    {
        config(['services.gateway.api_key' => 'gateway-test', 'tracking.legacy_auth_enabled' => false]);
        $vehicle = Vehicle::create(['name' => 'Test', 'plate_no' => 'SYNC-1', 'status' => 'active']);
        $device = Device::create([
            'device_id' => 'SYNC-1', 'vehicle_id' => $vehicle->id, 'vehicle_plate' => 'SYNC-1',
        ]);
        $url = '/api/gps/sync-status';
        $payload = ['devices' => [[
            'device_id' => $device->device_id,
            'state' => 'receiving', 'pending_count' => 3, 'review_count' => 0,
        ]]];
        $this->postJson($url, $payload)->assertUnauthorized();
        $this->withHeader('X-Gateway-Key', 'gateway-test')->postJson($url, $payload)->assertNoContent();

        Sanctum::actingAs(User::factory()->create(['role' => 'driver']));
        $this->getJson('/api/gps/sync-logs?summary=1')->assertForbidden();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/gps/sync-logs?summary=1')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonCount(1, 'transfers')
            ->assertJsonPath('transfers.0.device_registry_id', $device->id)
            ->assertJsonPath('transfers.0.state', 'receiving')
            ->assertJsonPath('transfers.0.pending_count', 3)
            ->assertJsonStructure(['transfers' => [['reported_at']]]);

        $payload['devices'][0]['state'] = 'uploaded';
        $payload['devices'][0]['pending_count'] = 0;
        $this->postJson($url, $payload)->assertNoContent();
        $this->getJson('/api/gps/sync-logs?summary=1')->assertOk()
            ->assertJsonCount(1, 'transfers')
            ->assertJsonPath('transfers.0.state', 'uploaded');
        $this->assertDatabaseCount('gps_locations', 0);
    }
}
