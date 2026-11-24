<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Vehicle;
use App\Models\GpsLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GpsIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.gateway.api_key' => 'test-gateway',
            'services.tracker_legacy.api_key' => 'test-legacy',
            'tracking.legacy_auth_enabled' => true,
            'tracking.legacy_device_id' => 'VEHICLE-01',
        ]);
        $this->withHeader('X-Gateway-Key', 'test-legacy');

        $vehicle = Vehicle::create([
            'name' => 'Test',
            'plate_no' => 'TEST-1',
            'status' => 'active',
        ]);

        Device::create([
            'device_id' => 'VEHICLE-01',
            'vehicle_id' => $vehicle->id,
            'vehicle_plate' => 'TEST-1',
        ]);
    }

    private function payload(): array
    {
        return [
            'device_id' => 'VEHICLE-01',
            'record_id' => 'VEHICLE-01-1',
            'device_sequence' => 1,
            'captured_at' => '2026-01-01T00:00:00Z',
            'latitude' => 10.589795,
            'longitude' => 123.476213,
            'satellites' => 12,
        ];
    }

    private function gps(array $payload)
    {
        $key = ($payload['upload_source'] ?? null) === 'recovered_lora'
            ? 'test-gateway'
            : 'test-legacy';

        return $this->withHeader('X-Gateway-Key', $key)
            ->postJson('/api/gps/location', $payload);
    }

    public function test_identical_retry_preserves_record_and_receipt(): void
    {
        $this->gps($this->payload())
            ->assertCreated()
            ->assertJsonPath('duplicate', false);

        $original = GpsLocation::first()->getAttributes();

        $this->travel(1)->minutes();

        $retry = $this->payload();
        $retry['captured_at'] = '2026-01-01T08:00:00.999+08:00';
        $retry['latitude'] += 0.00000001;

        $this->gps($retry)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('acknowledged_record_id', 'VEHICLE-01-1');

        $this->assertDatabaseCount('gps_locations', 1);
        $this->assertSame(
            $original,
            GpsLocation::first()->getAttributes()
        );
    }

    public function test_conflicting_retries_do_not_change_saved_data_or_device(): void
    {
        $this->gps($this->payload())
            ->assertCreated();

        $original = GpsLocation::first()->getAttributes();
        $device = Device::first()->getAttributes();

        $this->travel(1)->minutes();

        foreach ([
            'device_sequence' => 2,
            'satellites' => 11,
            'latitude' => 10.5897951,
            'longitude' => 123.4762131,
            'captured_at' => '2026-01-01T00:00:01Z',
        ] as $key => $value) {
            $this->gps(array_replace($this->payload(), [$key => $value]))
                ->assertStatus(409);
        }

        $retry = $this->payload();
        unset($retry['satellites']);

        $this->gps($retry)
            ->assertStatus(409);

        $this->assertSame(
            $original,
            GpsLocation::first()->getAttributes()
        );

        $this->assertSame(
            $device,
            Device::first()->getAttributes()
        );
    }

    public function test_invalid_values_are_rejected_before_insertion(): void
    {
        foreach ([
            'device_sequence' => '9223372036854775808',
            'latitude' => 91,
            'longitude' => -181,
            'satellites' => 256,
            'captured_at' => 'not-a-date',
            'record_id' => 'OTHER-1',
        ] as $key => $value) {
            $this->postJson(
                '/api/gps/location',
                array_replace($this->payload(), [$key => $value])
            )->assertUnprocessable();
        }

        $this->postJson(
            '/api/gps/location',
            array_replace($this->payload(), [
                'captured_at' => now()->addMinutes(6)->toISOString(),
            ])
        )->assertUnprocessable();

        $this->assertDatabaseCount('gps_locations', 0);
    }

    public function test_legacy_payload_and_legacy_authentication(): void
    {
        $payload = $this->payload();

        unset(
            $payload['record_id'],
            $payload['device_sequence'],
            $payload['captured_at']
        );

        $this->withHeader('X-Gateway-Key', 'wrong')
            ->postJson('/api/gps/location', $payload)
            ->assertUnauthorized();

        $this->assertDatabaseCount('gps_locations', 0);

        $this->withHeader('X-Gateway-Key', 'test-legacy')
            ->postJson('/api/gps/location', $payload)
            ->assertCreated()
            ->assertJsonPath('acknowledged_record_id', null);

        $this->assertDatabaseCount('gps_locations', 1);
    }

    public function test_recovered_and_stale_uploads_do_not_mark_device_online(): void
    {
        foreach ([
            'recovered_lte',
            'recovered_lora',
            'live_lte',
            null,
        ] as $index => $source) {
            $payload = array_replace($this->payload(), [
                'record_id' => 'VEHICLE-01-source-'.$index,
                'device_sequence' => $index + 1,
                'upload_source' => $source,
            ]);

            $this->gps($payload)
                ->assertCreated()
                ->assertJsonPath('location.upload_source', $source);

            $this->assertFalse(Device::first()->is_online);
            $this->assertNull(Device::first()->last_seen);
        }
    }

    public function test_fresh_live_upload_marks_online_but_cross_transport_retry_does_not(): void
    {
        $this->freezeTime();

        $payload = array_replace($this->payload(), [
            'captured_at' => now('UTC')->toISOString(),
            'upload_source' => 'live_lte',
        ]);

        $this->gps($payload)
            ->assertCreated();

        $this->assertTrue(Device::first()->is_online);

        $original = GpsLocation::first()->getAttributes();

        Device::first()->update(['is_online' => false]);

        $lastSeen = Device::first()->getRawOriginal('last_seen');

        $this->travel(1)->minutes();

        $payload['upload_source'] = 'recovered_lora';

        $this->gps($payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertFalse(Device::first()->is_online);

        $this->assertSame(
            $lastSeen,
            Device::first()->getRawOriginal('last_seen')
        );

        $this->assertSame(
            $original,
            GpsLocation::first()->getAttributes()
        );
    }

    public function test_recovered_fresh_sample_does_not_mark_online_and_source_requires_capture_time(): void
    {
        $payload = array_replace($this->payload(), [
            'captured_at' => now('UTC')->toISOString(),
            'upload_source' => 'recovered_lte',
        ]);

        $this->gps($payload)
            ->assertCreated();

        $this->assertFalse(Device::first()->is_online);

        unset($payload['captured_at']);

        $this->gps($payload)
            ->assertUnprocessable();

        $payload['upload_source'] = 'invalid';

        $this->postJson('/api/gps/location', $payload)
            ->assertUnprocessable();
    }

    public function test_recovered_uploads_require_record_id_and_sequence(): void
    {
        foreach (['recovered_lte', 'recovered_lora'] as $source) {
            $payload = array_replace($this->payload(), [
                'upload_source' => $source,
            ]);

            $missingRecordId = $payload;
            unset($missingRecordId['record_id']);

            $this->gps($missingRecordId)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['record_id']);

            $missingSequence = $payload;
            unset($missingSequence['device_sequence']);

            $this->gps($missingSequence)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['device_sequence']);

            $missingBoth = $payload;

            unset(
                $missingBoth['record_id'],
                $missingBoth['device_sequence']
            );

            $this->gps($missingBoth)
                ->assertUnprocessable()
                ->assertJsonValidationErrors([
                    'record_id',
                    'device_sequence',
                ]);
        }

        $this->assertDatabaseCount('gps_locations', 0);
    }

    public function test_same_device_cannot_reuse_sequence_with_different_record_id(): void
    {
        $payload = array_replace($this->payload(), [
            'upload_source' => 'recovered_lte',
        ]);

        $this->gps($payload)
            ->assertCreated();

        $original = GpsLocation::first()->getAttributes();
        $device = Device::first()->getAttributes();

        $conflicting = array_replace($payload, [
            'record_id' => 'VEHICLE-01-conflict',
            'upload_source' => 'recovered_lora',
        ]);

        $this->gps($conflicting)
            ->assertStatus(409);

        $this->assertDatabaseCount('gps_locations', 1);

        $this->assertSame(
            $original,
            GpsLocation::first()->getAttributes()
        );

        $this->assertSame(
            $device,
            Device::first()->getAttributes()
        );

        $payload['upload_source'] = 'recovered_lora';

        $this->gps($payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath(
                'acknowledged_record_id',
                'VEHICLE-01-1'
            );

        $this->assertDatabaseCount('gps_locations', 1);
    }

    public function test_different_devices_can_use_same_sequence(): void
    {
        $vehicle = Vehicle::create([
            'name' => 'Second Test Vehicle',
            'plate_no' => 'TEST-2',
            'status' => 'active',
        ]);

        Device::create([
            'device_id' => 'VEHICLE-02',
            'vehicle_id' => $vehicle->id,
            'vehicle_plate' => 'TEST-2',
        ]);

        $firstPayload = array_replace($this->payload(), [
            'upload_source' => 'recovered_lte',
        ]);

        $secondPayload = array_replace($this->payload(), [
            'device_id' => 'VEHICLE-02',
            'record_id' => 'VEHICLE-02-1',
            'device_sequence' => 1,
            'upload_source' => 'recovered_lora',
        ]);

        $this->gps($firstPayload)
            ->assertCreated();

        $this->gps($secondPayload)
            ->assertCreated();

        $this->assertDatabaseCount('gps_locations', 2);

        $this->assertDatabaseHas('gps_locations', [
            'record_id' => 'VEHICLE-01-1',
            'device_sequence' => 1,
        ]);

        $this->assertDatabaseHas('gps_locations', [
            'record_id' => 'VEHICLE-02-1',
            'device_sequence' => 1,
        ]);
    }

    public function test_offline_records_can_arrive_out_of_sequence_order(): void
    {
        foreach ([3, 1, 2] as $sequence) {
            $payload = array_replace($this->payload(), [
                'record_id' => 'VEHICLE-01-'.$sequence,
                'device_sequence' => $sequence,
                'upload_source' => 'recovered_lora',
            ]);

            $this->gps($payload)
                ->assertCreated()
                ->assertJsonPath('duplicate', false)
                ->assertJsonPath(
                    'acknowledged_record_id',
                    'VEHICLE-01-'.$sequence
                );
        }

        $this->assertDatabaseCount('gps_locations', 3);

        $this->assertSame(
            [1, 2, 3],
            GpsLocation::orderBy('device_sequence')
                ->pluck('device_sequence')
                ->all()
        );

        $this->assertFalse(Device::first()->is_online);
    }
}
