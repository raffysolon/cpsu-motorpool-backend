<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\GpsLocation;
use App\Models\Notification;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\TripDepartureMonitor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripDepartureMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fleet(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::create(['name' => 'Test vehicle', 'plate_no' => 'TEST-2', 'status' => 'active']);
        $device = Device::create(['device_id' => 'Device 2', 'vehicle_id' => $vehicle->id,
            'vehicle_plate' => $vehicle->plate_no, 'registered_at' => now(), 'is_online' => true]);
        $trip = Trip::create(['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
            'origin' => 'Campus', 'destination' => 'Office', 'purpose' => 'Test',
            'scheduled_departure' => '2026-11-18 09:00:00', 'status' => 'approved']);
        return [$admin, $driver, $vehicle, $device, $trip];
    }

    public function test_alert_waits_five_minutes_and_is_not_duplicated_after_read(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 09:04:59', 'Asia/Manila'));
        [$admin, , , , $trip] = $this->fleet();
        $monitor = app(TripDepartureMonitor::class);
        $monitor->check();
        $this->assertDatabaseCount('notifications', 0);
        Carbon::setTestNow(Carbon::parse('2026-11-18T01:05:00Z'));
        $monitor->check();
        $notification = Notification::firstOrFail();
        $this->assertSame($admin->id, $notification->user_id);
        $this->assertStringContainsString("Device 2 hasn't started trip #{$trip->id} yet", $notification->message);
        $notification->update(['is_read' => true]);
        $monitor->check();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_actual_button_start_records_twelve_minutes_late_and_stops_overdue_alerts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 09:12:00', 'Asia/Manila'));
        [$admin, $driver, , , $trip] = $this->fleet();
        Sanctum::actingAs($driver);
        $this->postJson("/api/trips/{$trip->id}/start")
            ->assertOk()->assertJsonPath('late_minutes', 12)
            ->assertJsonPath('trip.movements.0.late_minutes', 12)
            ->assertJsonPath('trip.status', 'active');
        $notification = Notification::where('user_id', $admin->id)->firstOrFail();
        $this->assertStringContainsString('12 minute(s) late', $notification->message);
        $this->assertStringContainsString('Device 2', $notification->message);
        app(TripDepartureMonitor::class)->check();
        $this->assertDatabaseCount('notifications', 1);
        $this->postJson("/api/trips/{$trip->id}/start")->assertUnprocessable();
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_early_start_has_zero_lateness_and_no_overdue_notification(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 08:55:00', 'Asia/Manila'));
        [, $driver, , , $trip] = $this->fleet();
        Sanctum::actingAs($driver);
        $this->postJson("/api/trips/{$trip->id}/start")->assertOk()
            ->assertJsonPath('late_minutes', 0)->assertJsonPath('start_timing', 'early');
        Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00', 'Asia/Manila'));
        app(TripDepartureMonitor::class)->check();
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_live_gps_is_visible_without_a_scheduled_trip_and_reports_real_trip_context(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 09:06:00', 'Asia/Manila'));
        [$admin, , $vehicle, $device, $trip] = $this->fleet();
        $trip->update(['status' => 'pending']);
        GpsLocation::create(['device_registry_id' => $device->id, 'vehicle_id' => $vehicle->id,
            'latitude' => 10.5, 'longitude' => 123.4, 'recorded_at' => now(), 'satellites' => 8]);
        Sanctum::actingAs($admin);
        $this->getJson("/api/gps/vehicles/{$vehicle->id}/latest")->assertOk()
            ->assertJsonPath('trip', null)->assertJsonPath('latitude', 10.5);
        $trip->update(['status' => 'approved']);
        $this->getJson("/api/gps/vehicles/{$vehicle->id}/latest")->assertOk()
            ->assertJsonPath('trip.id', $trip->id)->assertJsonPath('trip.departure_overdue', true)
            ->assertJsonPath('trip.scheduled_departure', '2026-11-18T01:00:00.000000Z');
        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('count', 1);
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/gps/sync-logs')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.device.device_id', 'Device 2');
    }

    public function test_pending_denied_and_completed_trips_never_raise_overdue_alerts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 09:20:00', 'Asia/Manila'));
        [, , , , $trip] = $this->fleet();
        foreach (['pending', 'denied', 'completed'] as $status) {
            $trip->update(['status' => $status]);
            app(TripDepartureMonitor::class)->check();
        }
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_rescheduling_starts_a_new_grace_period_and_alert_identity(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-18 09:10:00', 'Asia/Manila'));
        [, , , , $trip] = $this->fleet();
        $monitor = app(TripDepartureMonitor::class);
        $monitor->check();
        $this->assertDatabaseCount('notifications', 1);
        $trip->update(['scheduled_departure' => '2026-11-18 10:00:00']);
        $monitor->check();
        $this->assertDatabaseCount('notifications', 1);
        Carbon::setTestNow(Carbon::parse('2026-11-18 10:05:00', 'Asia/Manila'));
        $monitor->check();
        $monitor->check();
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_sync_logs_require_an_authenticated_admin(): void
    {
        $this->getJson('/api/gps/sync-logs')->assertUnauthorized();
        $driver = User::factory()->create(['role' => 'driver']);
        Sanctum::actingAs($driver);
        $this->getJson('/api/gps/sync-logs')->assertForbidden();
    }
}
