<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\GpsLocation;
use App\Models\Notification;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehicleMovementMonitor;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleMovementMonitorTest extends TestCase
{
    use RefreshDatabase;

    private function fleet(?string $tripStatus = null): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::create([
            'name' => 'Movement test vehicle',
            'plate_no' => 'MOVE-1',
            'status' => 'active',
        ]);
        $device = Device::create([
            'device_id' => 'MOVE-TRACKER',
            'vehicle_id' => $vehicle->id,
            'vehicle_plate' => $vehicle->plate_no,
        ]);
        $trip = null;
        if ($tripStatus !== null) {
            $trip = Trip::create([
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'origin' => 'Campus',
                'destination' => 'Test destination',
                'purpose' => 'Movement test',
                'scheduled_departure' => now()->addHour(),
                'status' => $tripStatus,
            ]);
        }

        return [$admin, $driver, $vehicle, $device, $trip];
    }

    private function movingFixes(Device $device, Vehicle $vehicle): void
    {
        $start = now('UTC')->subSeconds(45);
        for ($step = 0; $step < 4; $step++) {
            GpsLocation::create([
                'device_registry_id' => $device->id,
                'vehicle_id' => $vehicle->id,
                'latitude' => 10.6713,
                'longitude' => 122.9511 + ($step * 0.00023),
                'satellites' => 8,
                'recorded_at' => $start->copy()->addSeconds($step * 15),
                'received_at' => now('UTC'),
                'upload_source' => 'live_lte',
                'record_id' => "MOVE-TRACKER-{$step}",
                'device_sequence' => $step + 1,
            ]);
        }
    }

    private function recordFix(
        Device $device,
        Vehicle $vehicle,
        CarbonInterface $recordedAt,
        float $longitude,
        string $recordId
    ): GpsLocation {
        return GpsLocation::create([
            'device_registry_id' => $device->id,
            'vehicle_id' => $vehicle->id,
            'latitude' => 10.6713,
            'longitude' => $longitude,
            'satellites' => 8,
            'recorded_at' => $recordedAt,
            'received_at' => now('UTC'),
            'upload_source' => 'live_lte',
            'record_id' => $recordId,
            'device_sequence' => GpsLocation::count() + 1,
        ]);
    }

    public function test_sustained_movement_before_approved_trip_notifies_driver_and_admin_once(): void
    {
        [$admin, $driver, $vehicle, $device, $trip] = $this->fleet('approved');
        $this->movingFixes($device, $vehicle);

        $monitor = app(VehicleMovementMonitor::class);
        foreach (GpsLocation::orderBy('id')->get() as $location) {
            $monitor->observe($location);
        }
        $monitor->observe(GpsLocation::latest('id')->firstOrFail());
        $monitor->checkRecent();
        $monitor->checkRecent();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertEqualsCanonicalizing(
            [$admin->id, $driver->id],
            Notification::pluck('user_id')->all()
        );
        $this->assertSame(
            ['normal'],
            Notification::query()->distinct()->pluck('priority')->all()
        );
        $this->assertTrue(
            Notification::where('message', 'like', '%before trip%')->exists()
        );
        $this->assertSame('approved', $trip->fresh()->status);
        $this->assertSame(
            0,
            $trip->movements()->whereNotNull('actual_departure_at')->count()
        );
        $this->assertSame(
            2,
            Notification::query()->distinct()->count('event_key')
        );
    }

    public function test_a_separate_sustained_movement_incident_gets_a_distinct_alert(): void
    {
        [$admin, , $vehicle, $device] = $this->fleet();
        $this->movingFixes($device, $vehicle);
        $monitor = app(VehicleMovementMonitor::class);
        foreach (GpsLocation::orderBy('id')->get() as $location) {
            $monitor->observe($location);
        }

        $this->travel(15)->seconds();
        $stationary = $this->recordFix(
            $device,
            $vehicle,
            now('UTC'),
            122.9511 + (3 * 0.00023),
            'MOVE-TRACKER-STATIONARY'
        );
        $monitor->observe($stationary);

        $this->travel(75)->seconds();
        $baseline = $this->recordFix(
            $device,
            $vehicle,
            now('UTC'),
            122.9511 + (3 * 0.00023),
            'MOVE-TRACKER-SECOND-BASELINE'
        );
        $monitor->observe($baseline);

        for ($step = 1; $step <= 3; $step++) {
            $this->travel(15)->seconds();
            $location = $this->recordFix(
                $device,
                $vehicle,
                now('UTC'),
                122.9511 + ((3 + $step) * 0.00023),
                "MOVE-TRACKER-SECOND-{$step}"
            );
            $monitor->observe($location);
        }
        $monitor->checkRecent();
        $monitor->checkRecent();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame($admin->id, Notification::firstOrFail()->user_id);
        $this->assertSame(
            2,
            Notification::query()->distinct()->count('event_key')
        );
    }

    public function test_sustained_movement_without_a_trip_is_high_priority_for_admin_only(): void
    {
        [$admin, , $vehicle, $device] = $this->fleet();
        $this->movingFixes($device, $vehicle);

        $monitor = app(VehicleMovementMonitor::class);
        foreach (GpsLocation::orderBy('id')->get() as $location) {
            $monitor->observe($location);
        }

        $notification = Notification::firstOrFail();
        $this->assertSame($admin->id, $notification->user_id);
        $this->assertSame('high', $notification->priority);
        $this->assertStringContainsString('without an approved or active trip', $notification->message);
    }

    public function test_active_trip_movement_does_not_raise_an_unauthorized_alert(): void
    {
        [, , $vehicle, $device] = $this->fleet('active');
        $this->movingFixes($device, $vehicle);

        $monitor = app(VehicleMovementMonitor::class);
        foreach (GpsLocation::orderBy('id')->get() as $location) {
            $monitor->observe($location);
        }

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_new_live_gps_uploads_feed_movement_monitoring(): void
    {
        $this->fleet();
        config([
            'services.tracker_legacy.api_key' => 'movement-test-legacy',
            'tracking.legacy_auth_enabled' => true,
            'tracking.legacy_device_id' => 'MOVE-TRACKER',
        ]);
        $start = now('UTC')->subSeconds(45);

        for ($step = 0; $step < 4; $step++) {
            $this->withHeader('X-Gateway-Key', 'movement-test-legacy')
                ->postJson('/api/gps/location', [
                    'device_id' => 'MOVE-TRACKER',
                    'record_id' => 'MOVE-TRACKER-API-'.$step,
                    'device_sequence' => $step + 1,
                    'captured_at' => $start->copy()
                        ->addSeconds($step * 15)
                        ->toISOString(),
                    'upload_source' => 'live_lte',
                    'latitude' => 10.6713,
                    'longitude' => 122.9511 + ($step * 0.00023),
                    'satellites' => 8,
                ])->assertCreated();
        }

        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame('high', Notification::firstOrFail()->priority);
    }

    public function test_return_to_campus_reminder_requires_leave_and_stationary_evidence(): void
    {
        [, $driver, $vehicle, $device, $trip] = $this->fleet('active');
        config([
            'tracking.movement.campus_latitude' => 10.6713,
            'tracking.movement.campus_longitude' => 122.9511,
            'tracking.movement.campus_radius_meters' => 30,
        ]);
        $monitor = app(VehicleMovementMonitor::class);
        $points = [
            122.9511,
            122.95156,
            122.95202,
            122.9511,
            122.95111,
            122.9511,
        ];

        foreach ($points as $step => $longitude) {
            $timestamp = now('UTC');
            $location = GpsLocation::create([
                'device_registry_id' => $device->id,
                'vehicle_id' => $vehicle->id,
                'latitude' => 10.6713,
                'longitude' => $longitude,
                'satellites' => 8,
                'recorded_at' => $timestamp,
                'received_at' => $timestamp,
                'upload_source' => 'live_lte',
                'record_id' => "RETURN-TRACKER-{$step}",
                'device_sequence' => $step + 1,
            ]);
            $monitor->observe($location);
            if ($step < count($points) - 1) {
                $this->travel(15)->seconds();
            }
        }

        $this->assertDatabaseCount('notifications', 1);
        $notification = Notification::firstOrFail();
        $this->assertSame($driver->id, $notification->user_id);
        $this->assertStringContainsString('returned to campus', $notification->message);
        $this->assertSame('active', $trip->fresh()->status);
        $this->assertNull($trip->movements()->first()?->actual_arrival_at);
    }

    public function test_recovered_or_stale_gps_does_not_produce_current_movement_alerts(): void
    {
        [$admin, , $vehicle, $device] = $this->fleet();
        $this->movingFixes($device, $vehicle);
        GpsLocation::query()->update(['upload_source' => 'recovered_lora']);

        $monitor = app(VehicleMovementMonitor::class);
        foreach (GpsLocation::orderBy('id')->get() as $location) {
            $monitor->observe($location);
        }

        $this->assertDatabaseCount('notifications', 0);

        GpsLocation::query()->update([
            'upload_source' => 'live_lte',
            'recorded_at' => now('UTC')->subMinutes(5),
        ]);
        foreach (GpsLocation::orderBy('id')->get() as $location) {
            $monitor->observe($location);
        }

        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame('admin', $admin->role);
    }
}
