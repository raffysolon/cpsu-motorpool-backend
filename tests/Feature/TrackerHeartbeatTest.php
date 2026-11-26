<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TrackerHeartbeatMonitor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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
            ->assertJsonPath('is_online', true)
            ->assertJsonPath('availability_status', 'online');

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
            now('UTC')->subSeconds(61)->format('Y-m-d\TH:i:s\Z'),
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

    public function test_availability_boundaries_and_future_timestamps_are_calculated_from_heartbeat_time(): void
    {
        $device = Device::firstOrFail();
        $this->assertSame('unreachable', $device->availability_status);

        foreach ([
            0 => 'online',
            30 => 'online',
            60 => 'online',
            61 => 'unstable',
            90 => 'unstable',
            120 => 'unstable',
            121 => 'unreachable',
        ] as $age => $expectedStatus) {
            $device->forceFill([
                'last_heartbeat_at' => now('UTC')
                    ->subSeconds($age)
                    ->format('Y-m-d H:i:s'),
            ])->save();

            $this->assertSame(
                $expectedStatus,
                $device->fresh()->availability_status,
                "Unexpected availability at {$age} seconds."
            );
        }

        $device->forceFill([
            'last_heartbeat_at' => now('UTC')
                ->addSeconds(30)
                ->format('Y-m-d H:i:s'),
        ])->save();

        $this->assertSame('online', $device->fresh()->availability_status);
    }

    public function test_heartbeat_accepts_exactly_60_seconds_and_rejects_older_timestamps(): void
    {
        $sentAt = now('UTC')->subSeconds(60)->format('Y-m-d\TH:i:s\Z');

        $this->heartbeat(['sent_at' => $sentAt])
            ->assertOk()
            ->assertJsonPath('ignored', false)
            ->assertJsonPath('availability_status', 'online');

        $storedHeartbeat = Device::firstOrFail()
            ->getRawOriginal('last_heartbeat_at');
        $storedLastSeen = Device::firstOrFail()
            ->getRawOriginal('last_seen');

        $this->heartbeat([
            'sent_at' => now('UTC')->subSeconds(61)->format('Y-m-d\TH:i:s\Z'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['sent_at']);

        $device = Device::firstOrFail();
        $this->assertSame($storedHeartbeat, $device->getRawOriginal('last_heartbeat_at'));
        $this->assertSame($storedLastSeen, $device->getRawOriginal('last_seen'));
    }

    public function test_future_heartbeat_is_clamped_and_blocks_replays_until_a_newer_timestamp_arrives(): void
    {
        $futureSentAt = now('UTC')
            ->addSeconds(30)
            ->format('Y-m-d\TH:i:s\Z');

        $this->heartbeat(['sent_at' => $futureSentAt])
            ->assertOk()
            ->assertJsonPath('ignored', false)
            ->assertJsonPath('availability_status', 'online');

        $accepted = Device::firstOrFail();
        $storedHeartbeat = $accepted->getRawOriginal('last_heartbeat_at');
        $storedLastSeen = $accepted->getRawOriginal('last_seen');
        $storedReceivedAt = $accepted->getRawOriginal(
            'last_heartbeat_received_at'
        );

        $this->travel(10)->seconds();

        $this->heartbeat(['sent_at' => $futureSentAt])
            ->assertOk()
            ->assertJsonPath('ignored', true)
            ->assertJsonPath('availability_status', 'online');

        $this->heartbeat([
            'sent_at' => now('UTC')->format('Y-m-d\TH:i:s\Z'),
        ])
            ->assertOk()
            ->assertJsonPath('ignored', true);

        $this->assertSame(
            $storedHeartbeat,
            Device::firstOrFail()->getRawOriginal('last_heartbeat_at')
        );
        $this->assertSame(
            $storedLastSeen,
            Device::firstOrFail()->getRawOriginal('last_seen')
        );
        $this->assertSame(
            $storedReceivedAt,
            Device::firstOrFail()->getRawOriginal('last_heartbeat_received_at')
        );

        $this->travel(21)->seconds();

        $this->heartbeat()
            ->assertOk()
            ->assertJsonPath('ignored', false)
            ->assertJsonPath('availability_status', 'online');
    }

    public function test_future_sender_time_does_not_extend_availability_past_server_receipt_time(): void
    {
        $this->heartbeat([
            'sent_at' => now('UTC')->addSeconds(30)->format('Y-m-d\TH:i:s\Z'),
        ])->assertOk();

        $device = Device::firstOrFail();
        $this->assertSame(
            now('UTC')->format('Y-m-d H:i:s'),
            $device->getRawOriginal('last_heartbeat_received_at')
        );
        $this->assertSame(
            now('UTC')->addSeconds(30)->format('Y-m-d H:i:s'),
            $device->getRawOriginal('last_heartbeat_at')
        );

        $this->travel(61)->seconds();

        $this->assertSame('unstable', $device->fresh()->availability_status);

        $this->travel(60)->seconds();

        $this->assertSame('unreachable', $device->fresh()->availability_status);
    }

    public function test_delayed_sender_time_does_not_become_fresh_at_server_receipt(): void
    {
        $device = Device::firstOrFail();
        $device->forceFill([
            'last_heartbeat_at' => now('UTC')
                ->subSeconds(90)
                ->format('Y-m-d H:i:s'),
            'last_heartbeat_received_at' => now('UTC')
                ->format('Y-m-d H:i:s'),
        ])->save();

        $this->assertSame('unstable', $device->fresh()->availability_status);

        $this->travel(31)->seconds();

        $this->assertSame('unreachable', $device->fresh()->availability_status);
    }

    public function test_last_seen_and_utc_heartbeat_serialize_to_the_same_philippine_instant(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        $device = Device::firstOrFail();
        $device->forceFill([
            'last_seen' => Carbon::parse('2026-11-24 20:33:00', 'Asia/Manila'),
            'last_heartbeat_at' => '2026-11-24 12:33:00',
            'last_heartbeat_received_at' => '2026-11-24 12:33:00',
        ])->save();

        $serialized = $device->fresh()->toArray();
        $lastSeen = Carbon::parse($serialized['last_seen']);
        $heartbeatReceived = Carbon::parse(
            $serialized['last_heartbeat_received_at'],
            'UTC'
        );

        $this->assertSame(
            '2026-11-24 20:33:00',
            $device->fresh()->getRawOriginal('last_seen')
        );
        $this->assertSame(
            $heartbeatReceived->getTimestamp(),
            $lastSeen->getTimestamp()
        );
        $this->assertSame(20, $lastSeen->copy()->timezone('Asia/Manila')->hour);
        $this->assertSame(
            '2026-11-24 12:33:00',
            $device->fresh()->getRawOriginal('last_heartbeat_at')
        );
    }

    public function test_device_api_adds_availability_and_preserves_existing_fields_and_hidden_hash(): void
    {
        Device::firstOrFail()->forceFill([
            'tracker_key_hash' => str_repeat('f', 64),
        ])->save();

        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
        ]), ['*']);

        $this->getJson('/api/devices')
            ->assertOk()
            ->assertJsonPath('data.0.device_id', 'TRACKER-1')
            ->assertJsonPath('data.0.availability_status', 'unreachable')
            ->assertJsonPath('data.0.is_online', false)
            ->assertJsonPath('data.0.last_seen', null)
            ->assertJsonPath('data.0.last_heartbeat_at', null)
            ->assertJsonMissingPath('data.0.tracker_key_hash');
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
            ->assertJsonPath('is_online', true)
            ->assertJsonPath('availability_status', 'online');
    }

    public function test_recovered_gps_cannot_revive_offline_tracker(): void
    {
        $this->heartbeat()->assertOk();

        $this->travel(5)->minutes();

        app(TrackerHeartbeatMonitor::class)->check();

        $this->assertFalse(Device::first()->is_online);
        $this->assertSame(
            'unreachable',
            Device::first()->availability_status
        );
        $lastHeartbeatAt = Device::first()
            ->getRawOriginal('last_heartbeat_at');

        $this->withHeader('X-Gateway-Key', 'test-gateway')
            ->postJson('/api/gps/location', [
                'device_id' => 'TRACKER-1',
                'record_id' => 'TRACKER-1-1',
                'device_sequence' => 1,
                'latitude' => 10,
                'longitude' => 123,
                'satellites' => 5,
                'upload_source' => 'recovered_lora',
                'captured_at' => now()->toISOString(),
            ])
            ->assertCreated()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath(
                'acknowledged_record_id',
                'TRACKER-1-1'
            );

        $this->assertDatabaseCount('gps_locations', 1);
        $this->assertFalse(Device::first()->is_online);
        $this->assertSame(
            'unreachable',
            Device::first()->availability_status
        );
        $this->assertSame(
            $lastHeartbeatAt,
            Device::first()->getRawOriginal('last_heartbeat_at')
        );
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
                ->subSeconds(60)
                ->format('Y-m-d\TH:i:sP'),
        ])
            ->assertOk()
            ->assertJsonPath('ignored', false);

        $this->assertSame(
            now('UTC')->subSeconds(60)->format('Y-m-d H:i:s'),
            Device::first()->getRawOriginal('last_heartbeat_at')
        );

        $this->heartbeat([
            'sent_at' => now()
                ->addSeconds(30)
                ->format('Y-m-d\TH:i:sP'),
        ])->assertOk();
    }
}
