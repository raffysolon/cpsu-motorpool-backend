<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private string $trackerOneKey;
    private string $trackerTwoKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trackerOneKey = str_repeat('a', 64);
        $this->trackerTwoKey = str_repeat('b', 64);

        config([
            'services.gateway.api_key' => 'trusted-gateway-test-key',
            'services.tracker_legacy.api_key' => 'legacy-tracker-test-key',
            'tracking.legacy_auth_enabled' => true,
            'tracking.legacy_device_id' => 'VEHICLE-01',
        ]);

        $vehicle = Vehicle::create([
            'name' => 'Authorization test vehicle',
            'plate_no' => 'AUTHZ-1',
            'status' => 'active',
        ]);

        foreach ([
            'TRACKER-1' => $this->trackerOneKey,
            'TRACKER-2' => $this->trackerTwoKey,
        ] as $deviceId => $credential) {
            $device = Device::create([
                'device_id' => $deviceId,
                'vehicle_id' => $vehicle->id,
                'vehicle_plate' => $vehicle->plate_no,
            ]);

            $device->forceFill([
                'tracker_key_hash' => hash('sha256', $credential),
            ])->save();
        }

        Device::create([
            'device_id' => 'VEHICLE-01',
            'vehicle_id' => $vehicle->id,
            'vehicle_plate' => $vehicle->plate_no,
        ]);
    }

    public function test_individual_tracker_can_upload_live_lte_gps(): void
    {
        $this->withHeader('X-Tracker-Key', $this->trackerOneKey)
            ->postJson('/api/gps/location', $this->gpsPayload(
                'TRACKER-1',
                'live_lte'
            ))
            ->assertCreated()
            ->assertJsonPath('location.upload_source', 'live_lte');

        $this->assertTrue(
            Device::where('device_id', 'TRACKER-1')->firstOrFail()->is_online
        );
    }

    public function test_individual_tracker_can_upload_recovered_lte_without_reviving_connectivity(): void
    {
        $this->withHeader('X-Tracker-Key', $this->trackerOneKey)
            ->postJson('/api/gps/location', $this->gpsPayload(
                'TRACKER-1',
                'recovered_lte'
            ))
            ->assertCreated()
            ->assertJsonPath('location.upload_source', 'recovered_lte');

        $device = Device::where('device_id', 'TRACKER-1')->firstOrFail();

        $this->assertFalse($device->is_online);
        $this->assertNull($device->last_seen);
    }

    public function test_individual_tracker_can_send_its_heartbeat(): void
    {
        $this->withHeader('X-Tracker-Key', $this->trackerOneKey)
            ->postJson('/api/devices/heartbeat', [
                'device_id' => 'TRACKER-1',
                'sent_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            ])
            ->assertOk()
            ->assertJsonPath('device_id', 'TRACKER-1');
    }

    public function test_trusted_gateway_can_forward_recovered_lora_without_reviving_connectivity(): void
    {
        $this->withHeader('X-Gateway-Key', 'trusted-gateway-test-key')
            ->postJson('/api/gps/location', $this->gpsPayload(
                'TRACKER-1',
                'recovered_lora'
            ))
            ->assertCreated()
            ->assertJsonPath('location.upload_source', 'recovered_lora');

        $device = Device::where('device_id', 'TRACKER-1')->firstOrFail();

        $this->assertFalse($device->is_online);
        $this->assertNull($device->last_seen);
    }

    public function test_gateway_cannot_send_vehicle_heartbeats(): void
    {
        $this->withHeader('X-Gateway-Key', 'trusted-gateway-test-key')
            ->postJson('/api/devices/heartbeat', [
                'device_id' => 'TRACKER-1',
                'sent_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            ])
            ->assertStatus(403);
    }

    public function test_gateway_cannot_submit_live_or_recovered_lte_records(): void
    {
        foreach (['live_lte', 'recovered_lte'] as $source) {
            $this->withHeader('X-Gateway-Key', 'trusted-gateway-test-key')
                ->postJson('/api/gps/location', $this->gpsPayload(
                    'TRACKER-1',
                    $source
                ))
                ->assertStatus(403);
        }

        $this->assertDatabaseCount('gps_locations', 0);
    }

    public function test_individual_tracker_cannot_submit_recovered_lora_records(): void
    {
        $this->withHeader('X-Tracker-Key', $this->trackerOneKey)
            ->postJson('/api/gps/location', $this->gpsPayload(
                'TRACKER-1',
                'recovered_lora'
            ))
            ->assertStatus(403);

        $this->assertDatabaseCount('gps_locations', 0);
    }

    public function test_individual_tracker_cannot_impersonate_another_device(): void
    {
        $this->withHeader('X-Tracker-Key', $this->trackerOneKey)
            ->postJson('/api/gps/location', $this->gpsPayload(
                'TRACKER-2',
                'live_lte'
            ))
            ->assertUnauthorized();

        $this->withHeader('X-Tracker-Key', $this->trackerOneKey)
            ->postJson('/api/devices/heartbeat', [
                'device_id' => 'TRACKER-2',
                'sent_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            ])
            ->assertUnauthorized();

        $this->assertDatabaseCount('gps_locations', 0);
        $this->assertNull(
            Device::where('device_id', 'TRACKER-2')->value('last_seen')
        );
    }

    public function test_disabled_legacy_authentication_rejects_the_legacy_key(): void
    {
        config(['tracking.legacy_auth_enabled' => false]);

        $this->withHeader('X-Gateway-Key', 'legacy-tracker-test-key')
            ->postJson('/api/gps/location', [
                'device_id' => 'VEHICLE-01',
                'latitude' => 10,
                'longitude' => 123,
            ])
            ->assertUnauthorized();

        $this->withHeader('X-Gateway-Key', 'legacy-tracker-test-key')
            ->postJson('/api/devices/heartbeat', [
                'device_id' => 'VEHICLE-01',
                'sent_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            ])
            ->assertUnauthorized();

        $this->withHeader('X-Gateway-Key', 'trusted-gateway-test-key')
            ->postJson('/api/gps/location', $this->gpsPayload(
                'TRACKER-1',
                'recovered_lora'
            ))
            ->assertCreated();
    }

    public function test_explicit_legacy_compatibility_allows_legacy_lte_and_heartbeat_requests(): void
    {
        $this->withHeader('X-Gateway-Key', 'legacy-tracker-test-key')
            ->postJson('/api/gps/location', [
                'device_id' => 'VEHICLE-01',
                'latitude' => 10,
                'longitude' => 123,
            ])
            ->assertCreated();

        $this->withHeader('X-Gateway-Key', 'legacy-tracker-test-key')
            ->postJson('/api/devices/heartbeat', [
                'device_id' => 'VEHICLE-01',
                'sent_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            ])
            ->assertOk();

        $this->withHeader('X-Gateway-Key', 'legacy-tracker-test-key')
            ->postJson('/api/gps/location', $this->gpsPayload(
                'VEHICLE-01',
                'recovered_lora'
            ))
            ->assertStatus(403);
    }

    public function test_legacy_key_cannot_impersonate_a_different_device(): void
    {
        $this->withHeader('X-Gateway-Key', 'legacy-tracker-test-key')
            ->postJson('/api/gps/location', $this->gpsPayload(
                'TRACKER-2',
                'live_lte'
            ))
            ->assertUnauthorized();

        $this->withHeader('X-Gateway-Key', 'legacy-tracker-test-key')
            ->postJson('/api/devices/heartbeat', [
                'device_id' => 'TRACKER-2',
                'sent_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            ])
            ->assertUnauthorized();

        $this->assertDatabaseCount('gps_locations', 0);
        $this->assertNull(
            Device::where('device_id', 'TRACKER-2')->value('last_seen')
        );
    }

    public function test_legacy_key_is_rejected_when_no_device_id_is_configured(): void
    {
        config(['tracking.legacy_device_id' => null]);

        $this->withHeader('X-Gateway-Key', 'legacy-tracker-test-key')
            ->postJson('/api/gps/location', [
                'device_id' => 'VEHICLE-01',
                'latitude' => 10,
                'longitude' => 123,
            ])
            ->assertUnauthorized();

        $this->withHeader('X-Gateway-Key', 'legacy-tracker-test-key')
            ->postJson('/api/devices/heartbeat', [
                'device_id' => 'VEHICLE-01',
                'sent_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            ])
            ->assertUnauthorized();
    }

    public function test_ambiguous_gateway_and_legacy_credentials_are_rejected(): void
    {
        config([
            'services.tracker_legacy.api_key' => 'trusted-gateway-test-key',
        ]);

        $this->withHeader('X-Gateway-Key', 'trusted-gateway-test-key')
            ->postJson('/api/gps/location', $this->gpsPayload(
                'TRACKER-1',
                'recovered_lora'
            ))
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'Gateway and legacy authentication credentials must be distinct.'
            );

        $this->assertDatabaseCount('gps_locations', 0);
    }

    public function test_client_supplied_caller_type_cannot_override_server_authentication(): void
    {
        $this->withHeaders([
            'X-Tracker-Key' => $this->trackerOneKey,
            'X-Authenticated-Caller-Type' => 'trusted_gateway',
        ])->postJson('/api/gps/location', array_merge(
            $this->gpsPayload('TRACKER-1', 'recovered_lora'),
            ['authenticated_caller_type' => 'trusted_gateway']
        ))
            ->assertStatus(403);

        $this->assertDatabaseCount('gps_locations', 0);
    }

    public function test_gateway_recovered_lora_requires_record_sequence_and_capture_time(): void
    {
        $this->withHeader('X-Gateway-Key', 'trusted-gateway-test-key')
            ->postJson('/api/gps/location', [
                'device_id' => 'TRACKER-1',
                'upload_source' => 'recovered_lora',
                'latitude' => 10,
                'longitude' => 123,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'record_id',
                'device_sequence',
                'captured_at',
            ]);
    }

    private function gpsPayload(string $deviceId, string $source): array
    {
        $payload = [
            'device_id' => $deviceId,
            'upload_source' => $source,
            'latitude' => 10,
            'longitude' => 123,
        ];

        if ($source === 'live_lte') {
            $payload['captured_at'] = now('UTC')->subMinute()->toISOString();
        }

        if (in_array($source, ['recovered_lte', 'recovered_lora'], true)) {
            $payload['record_id'] = $deviceId.'-'.str_replace('_', '-', $source).'-1';
            $payload['device_sequence'] = 1;
            $payload['captured_at'] = now('UTC')->subMinute()->toISOString();
        }

        return $payload;
    }
}
