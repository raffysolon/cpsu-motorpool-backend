<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TrackerAuthenticationTest extends TestCase
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
            'services.gateway.api_key' => 'test-gateway-key',
            'services.tracker_legacy.api_key' => 'test-legacy-key',
            'tracking.legacy_auth_enabled' => true,
            'tracking.legacy_device_id' => 'TRACKER-1',
        ]);

        $vehicle = Vehicle::create([
            'name' => 'Test',
            'plate_no' => 'AUTH-1',
            'status' => 'active',
        ]);

        foreach ([
            'TRACKER-1' => $this->trackerOneKey,
            'TRACKER-2' => $this->trackerTwoKey,
        ] as $deviceId => $trackerKey) {
            $device = Device::create([
                'device_id' => $deviceId,
                'vehicle_id' => $vehicle->id,
                'vehicle_plate' => $vehicle->plate_no,
            ]);

            $device->forceFill([
                'tracker_key_hash' => hash('sha256', $trackerKey),
            ])->save();
        }
    }

    public function test_individual_credentials_authenticate_gps_and_heartbeat(): void
    {
        $this->withHeader('X-Tracker-Key', $this->trackerOneKey)
            ->postJson('/api/gps/location', [
                'device_id' => 'TRACKER-1',
                'upload_source' => 'live_lte',
                'captured_at' => now('UTC')->toISOString(),
                'latitude' => 10,
                'longitude' => 123,
            ])
            ->assertCreated();

        $this->withHeader('X-Tracker-Key', $this->trackerOneKey)
            ->postJson('/api/devices/heartbeat', [
                'device_id' => 'TRACKER-1',
                'sent_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            ])
            ->assertOk()
            ->assertJsonPath('device_id', 'TRACKER-1');
    }

    public function test_incorrect_or_unknown_tracker_credentials_are_rejected(): void
    {
        $this->withHeader('X-Tracker-Key', 'incorrect-key')
            ->postJson('/api/gps/location', [
                'device_id' => 'TRACKER-1',
                'latitude' => 10,
                'longitude' => 123,
            ])
            ->assertUnauthorized();

        $this->withHeader('X-Tracker-Key', $this->trackerOneKey)
            ->postJson('/api/devices/heartbeat', [
                'device_id' => 'UNKNOWN',
                'sent_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            ])
            ->assertUnauthorized();

        $this->assertDatabaseCount('gps_locations', 0);
        $this->assertNull(
            Device::where('device_id', 'TRACKER-1')->value('last_seen')
        );
    }

    public function test_requests_without_credentials_are_rejected(): void
    {
        $this->postJson('/api/gps/location', [
            'device_id' => 'TRACKER-1',
            'latitude' => 10,
            'longitude' => 123,
        ])->assertUnauthorized();

        $this->postJson('/api/devices/heartbeat', [
            'device_id' => 'TRACKER-1',
            'sent_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
        ])->assertUnauthorized();
    }

    public function test_tracker_cannot_authenticate_as_another_device(): void
    {
        $this->withHeader('X-Tracker-Key', $this->trackerOneKey)
            ->postJson('/api/gps/location', [
                'device_id' => 'TRACKER-2',
                'upload_source' => 'live_lte',
                'latitude' => 10,
                'longitude' => 123,
            ])
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

    public function test_invalid_tracker_key_never_falls_back_to_gateway_authentication(): void
    {
        $this->withHeaders([
            'X-Tracker-Key' => 'invalid-tracker-key',
            'X-Gateway-Key' => 'test-gateway-key',
        ])->postJson('/api/gps/location', [
            'device_id' => 'TRACKER-1',
            'latitude' => 10,
            'longitude' => 123,
        ])->assertUnauthorized();

        $this->withHeaders([
            'X-Tracker-Key' => 'invalid-tracker-key',
            'X-Gateway-Key' => 'test-gateway-key',
        ])->postJson('/api/devices/heartbeat', [
            'device_id' => 'TRACKER-1',
            'sent_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
        ])->assertUnauthorized();

        $this->assertDatabaseCount('gps_locations', 0);
        $this->assertNull(
            Device::where('device_id', 'TRACKER-1')->value('last_seen')
        );
    }

    public function test_malformed_and_repeated_tracker_headers_are_rejected(): void
    {
        foreach (['', 'not-hex', str_repeat('A', 64)] as $malformedKey) {
            $this->withHeaders([
                'X-Tracker-Key' => $malformedKey,
                'X-Gateway-Key' => 'test-gateway-key',
            ])->postJson('/api/gps/location', [
                'device_id' => 'TRACKER-1',
                'latitude' => 10,
                'longitude' => 123,
            ])->assertUnauthorized();
        }

        $request = Request::create('/api/gps/location', 'POST', [
            'device_id' => 'TRACKER-1',
        ]);
        $request->headers->set('X-Tracker-Key', [
            $this->trackerOneKey,
            $this->trackerTwoKey,
        ]);
        $request->headers->set('X-Gateway-Key', 'test-gateway-key');

        $response = app(\App\Http\Middleware\TrackerAuthentication::class)
            ->handle($request, fn () => response()->noContent(), 'gps');

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_repeated_gateway_headers_are_rejected(): void
    {
        $request = Request::create('/api/gps/location', 'POST', [
            'device_id' => 'TRACKER-1',
        ]);
        $request->headers->set('X-Gateway-Key', [
            'test-gateway-key',
            'ambiguous-value',
        ]);

        $response = app(\App\Http\Middleware\TrackerAuthentication::class)
            ->handle($request, fn () => response()->noContent(), 'gps');

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_legacy_key_can_be_disabled_without_authorizing_gateway_heartbeats(): void
    {
        $this->withHeader('X-Gateway-Key', 'test-legacy-key')
            ->postJson('/api/gps/location', [
                'device_id' => 'TRACKER-1',
                'latitude' => 10,
                'longitude' => 123,
            ])
            ->assertCreated();

        config(['tracking.legacy_auth_enabled' => false]);

        $this->withHeader('X-Gateway-Key', 'test-legacy-key')
            ->postJson('/api/devices/heartbeat', [
                'device_id' => 'TRACKER-1',
                'sent_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            ])
            ->assertUnauthorized();

        $this->withHeader('X-Gateway-Key', 'test-gateway-key')
            ->postJson('/api/devices/heartbeat', [
                'device_id' => 'TRACKER-1',
                'sent_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            ])
            ->assertStatus(403);
    }

    public function test_tracker_hash_is_not_mass_assignable_or_serialized(): void
    {
        $device = Device::where('device_id', 'TRACKER-1')->firstOrFail();
        $storedHash = $device->tracker_key_hash;

        $this->assertFalse($device->isFillable('tracker_key_hash'));
        $this->assertArrayNotHasKey('tracker_key_hash', $device->toArray());
        $this->assertArrayNotHasKey(
            'tracker_key_hash',
            json_decode($device->toJson(), true)
        );

        $device->fill([
            'tracker_key_hash' => str_repeat('a', 64),
        ]);

        $this->assertSame($storedHash, $device->tracker_key_hash);
    }
}
