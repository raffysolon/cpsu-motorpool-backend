<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_actions_record_people_and_times_in_order(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 08:00:00'));
        $driver = User::factory()->create(['role' => 'driver', 'name' => 'Driver Santos']);
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Reyes']);
        Sanctum::actingAs($driver);
        $id = $this->postJson('/api/trips', [
            'origin' => 'Campus A', 'destination' => 'Campus B', 'purpose' => 'Training',
            'scheduled_departure' => '2026-11-28 08:30:00',
        ])->assertCreated()->json('trip.id');
        $base = "/api/trips/$id";
        $this->getJson("$base/activity")->assertForbidden();

        $this->travel(5)->minutes();
        Sanctum::actingAs($admin);
        $this->putJson("$base/approve")->assertOk();
        $this->travel(30)->minutes();
        Sanctum::actingAs($driver);
        $this->postJson("$base/start")->assertOk();
        $this->postJson("$base/start")->assertUnprocessable();
        $this->travel(1)->hours();
        $this->postJson("$base/end")->assertOk();
        $this->travel(1)->hours();
        $this->postJson("$base/start-return")->assertOk();
        $this->travel(1)->hours();
        $this->postJson("$base/end-return")->assertOk();

        $driver->update(['name' => 'Renamed Driver']);
        Sanctum::actingAs($admin);
        $response = $this->getJson("$base/activity")->assertOk()
            ->assertJsonPath('events.0.actor_name', 'Driver Santos')
            ->assertJsonPath('events.1.actor_name', 'Admin Reyes')
            ->assertJsonPath('events.1.occurred_at', '2026-11-28T00:05:00.000000Z')
            ->assertJsonPath('events.2.late_minutes', 5)
            ->assertJsonPath('events.5.actor_name', 'Driver Santos')
            ->assertJsonPath('decision_time_missing', false);
        $this->assertSame([
            'submitted', 'approved', 'outbound_started', 'outbound_arrived', 'return_started', 'completed',
        ], array_column($response->json('events'), 'action'));

        Trip::findOrFail($id)->delete();
        $this->assertDatabaseCount('trip_activities', 0);
    }

    public function test_older_trip_uses_recorded_movements_without_inventing_an_approval(): void
    {
        $this->travelTo(Carbon::parse('2026-11-28 07:00:00'));
        $driver = User::factory()->create(['role' => 'driver']);
        $trip = Trip::create([
            'driver_id' => $driver->id, 'origin' => 'Campus A', 'destination' => 'Campus B',
            'purpose' => 'Training', 'scheduled_departure' => '2026-11-28 08:00:00',
            'source' => 'driver', 'status' => 'completed',
        ]);
        $trip->movements()->create([
            'movement_no' => 1, 'origin' => 'Campus A', 'destination' => 'Campus B',
            'actual_departure_at' => '2026-11-28 08:00:00', 'status' => 'completed',
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson("/api/trips/{$trip->id}/activity")->assertOk()
            ->assertJsonCount(2, 'events')
            ->assertJsonPath('events.0.action', 'submitted')
            ->assertJsonPath('events.1.action', 'outbound_started')
            ->assertJsonPath('events.1.actor_name', null)
            ->assertJsonPath('events.1.occurred_at', '2026-11-28T00:00:00.000000Z')
            ->assertJsonPath('decision_time_missing', true);
    }
}
