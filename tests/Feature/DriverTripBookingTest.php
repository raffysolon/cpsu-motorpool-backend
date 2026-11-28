<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverTripBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_pending_request_and_no_overlap_with_booked_round_trip(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $admin = User::factory()->create(['role' => 'admin']);
        $details = ['origin' => 'Office', 'destination' => 'Campus', 'purpose' => 'Training'];
        $nextDay = $details + [
            'scheduled_departure' => '2026-11-29 08:00:00',
            'return_scheduled_departure' => '2026-11-29 13:00:00',
            'scheduled_end_at' => '2026-11-29 14:30:00',
        ];
        $today = $details + [
            'scheduled_departure' => '2026-11-28 10:00:00',
            'scheduled_end_at' => '2026-11-28 11:00:00',
        ];

        Sanctum::actingAs($driver);
        $id = $this->postJson('/api/trips', $nextDay)->assertCreated()->json('trip.id');
        $this->postJson('/api/trips', $today)->assertUnprocessable()
            ->assertJsonPath('message', "Trip #{$id} to Campus is still pending. Wait for the admin decision before sending another request.");

        Sanctum::actingAs($admin);
        $this->putJson("/api/trips/{$id}/approve")->assertOk();
        Sanctum::actingAs($driver);
        $this->postJson('/api/trips', $details + ['scheduled_departure' => '2026-11-29 14:00:00'])
            ->assertUnprocessable()
            ->assertJsonPath('message', "Schedule overlaps trip #{$id} to Campus. Please choose a different time.");
        $this->postJson('/api/trips', $today)->assertCreated();
    }

    public function test_an_overrunning_trip_blocks_a_second_start_until_complete(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-28 10:00:00', 'Asia/Manila'));
        $driver = User::factory()->create(['role' => 'driver']);
        $details = [
            'driver_id' => $driver->id,
            'origin' => 'Office', 'destination' => 'Campus', 'purpose' => 'Training',
        ];
        $active = Trip::create($details + [
            'scheduled_departure' => '2026-11-28 08:00:00', 'status' => 'active',
        ]);
        $next = Trip::create($details + [
            'scheduled_departure' => '2026-11-28 10:00:00', 'status' => 'approved',
        ]);
        Sanctum::actingAs($driver);
        $this->postJson("/api/trips/{$next->id}/start")->assertUnprocessable()
            ->assertJsonPath('message', "Complete active trip #{$active->id} before starting another trip.");

        $active->update(['status' => 'completed']);
        $this->postJson("/api/trips/{$next->id}/start")->assertOk()
            ->assertJsonPath('trip.status', 'active');
    }
}
