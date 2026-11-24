<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Vehicle;
use App\Services\TrackerHeartbeatMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackerHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());

        config([
            'services.gateway.api_key' => 'test-gateway',
            'services.tracker_legacy.api_key' => 'test-legacy',
            'tracking.legacy_auth_enabled' => true,
            'tracking.legacy_device_id' => 'TRACKER-1',
        ]);
        $this->withHeader('X-Gateway-Key', 'test-legacy');

        $vehicle = Vehicle::create([
            'name' => 'Test',
            'plate_no' => 'HEART-1',
            'status' => 'active',
        ]);

        Device::create([
            'device_id' => 'TRACKER-1',
            'vehicle_id' => $vehicle->id,
            'vehicle_plate' => 'HEART-1',
        ]);
    }

    private function heartbeat(array $overrides = [])
    {
        return $this->postJson(
            '/api/devices/heartbeat',
            array_replace([
                'device_id' => 'TRACKER-1',
                'sent_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
            ], $overrides)
        );
    }

    public function test_heartbeat_works_without_gps_or_trip(): void
    {
        $this->heartbeat()
            ->assertOk()
            ->assertJsonPath('ignored', false)
            ->assertJsonPath('is_online', true);

        $device = Device::first();

        $this->assertNotNull($device->last_seen);
        $this->assertNotNull($device->last_heartbeat_at);
        $this->assertDatabaseCount('gps_locations', 0);
        $this->assertDatabaseCount('trips', 0);
    }

    public function test_authentication_and_unknown_device(): void
    {
        $this->withHeader('X-Gateway-Key', 'wrong');

        $this->heartbeat()->assertUnauthorized();

        $this->withHeader('X-Gateway-Key', 'test-legacy');

        $this->heartbeat(['device_id' => 'UNKNOWN'])
            ->assertUnauthorized();

        $this->assertFalse(Device::first()->is_online);
    }

    public function test_stale_future_ambiguous_and_missing_times_are_rejected(): void
    {
        foreach ([
            now('UTC')->subSeconds(91)->format('Y-m-d\TH:i:s\Z'),
            now('UTC')->addSeconds(31)->format('Y-m-d\TH:i:s\Z'),
            '2026-11-24 12:00:00',
            'not-a-date',
            null,
        ] as $time) {
            $this->heartbeat(['sent_at' => $time])
                ->assertUnprocessable();
        }

        $this->assertNull(Device::first()->last_seen);
    }

    public function test_replays_and_out_of_order_packets_do_not_extend_online_time(): void
    {
        $sent = now('UTC')->format('Y-m-d\TH:i:s\Z');

        $this->heartbeat()->assertOk();

        $before = Device::first()->getAttributes();

        $this->travel(20)->seconds();

        $this->heartbeat(['sent_at' => $sent])
            ->assertOk()
            ->assertJsonPath('ignored', true);

        $this->heartbeat([
            'sent_at' => now('UTC')
                ->subSeconds(30)
                ->format('Y-m-d\TH:i:s\Z'),
        ])
            ->assertOk()
            ->assertJsonPath('ignored', true);

        $this->assertSame(
            $before,
            Device::first()->getAttributes()
        );
    }

    public function test_monitor_expires_only_stale_devices_and_fresh_heartbeat_restores_online(): void
    {
        $this->heartbeat()->assertOk();

        $this->travel(299)->seconds();

        $this->assertSame(
            0,
            app(TrackerHeartbeatMonitor::class)->check()
        );

        $other = Device::create([
            'device_id' => 'TRACKER-2',
            'vehicle_id' => Device::first()->vehicle_id,
            'vehicle_plate' => 'HEART-1',
            'last_seen' => now(),
            'is_online' => true,
        ]);

        $this->travel(1)->seconds();

        $this->artisan('trackers:check-heartbeats')
            ->assertExitCode(0);

        $this->assertFalse(
            Device::where('device_id', 'TRACKER-1')
                ->first()
                ->is_online
        );

        $this->assertTrue($other->fresh()->is_online);

        $this->assertSame(
            0,
            app(TrackerHeartbeatMonitor::class)->check()
        );

        $this->heartbeat()
            ->assertOk()
            ->assertJsonPath('is_online', true);
    }

    public function test_recovered_gps_cannot_revive_offline_tracker(): void
    {
        $this->heartbeat()->assertOk();

        $this->travel(5)->minutes();

        app(TrackerHeartbeatMonitor::class)->check();

        $this->assertFalse(Device::first()->is_online);

        $this->withHeader('X-Gateway-Key', 'test-gateway')
            ->postJson('/api/gps/location', [
                'device_id' => 'TRACKER-1',
                'record_id' => 'TRACKER-1-1',
                'device_sequence' => 1,
                'latitude' => 10,
                'longitude' => 123,
                'satellites' => 5,
                'upload_source' => 'recovered_lora',
                'captured_at' => now()->subHour()->toISOString(),
            ])
            ->assertCreated()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath(
                'acknowledged_record_id',
                'TRACKER-1-1'
            );

        $this->assertDatabaseCount('gps_locations', 1);
        $this->assertFalse(Device::first()->is_online);
    }

    public function test_fresh_legacy_gps_remains_connectivity_evidence(): void
    {
        $this->postJson('/api/gps/location', [
            'device_id' => 'TRACKER-1',
            'latitude' => 10,
            'longitude' => 123,
        ])->assertCreated();

        $this->assertSame(
            0,
            app(TrackerHeartbeatMonitor::class)->check()
        );

        $this->assertTrue(Device::first()->is_online);
    }

    public function test_timezone_offset_is_normalized_and_timeout_boundaries_are_accepted(): void
    {
        $this->heartbeat([
            'sent_at' => now()
                ->subSeconds(90)
                ->format('Y-m-d\TH:i:sP'),
        ])
            ->assertOk()
            ->assertJsonPath('ignored', false);

        $this->assertSame(
            now('UTC')->subSeconds(90)->format('Y-m-d H:i:s'),
            Device::first()->getRawOriginal('last_heartbeat_at')
        );

        $this->heartbeat([
            'sent_at' => now()
                ->addSeconds(30)
                ->format('Y-m-d\TH:i:sP'),
        ])->assertOk();
    }
}
