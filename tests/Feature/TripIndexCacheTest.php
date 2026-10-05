<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripIndexCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_index_caches_each_query_and_invalidates_when_related_data_changes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $trip = Trip::create([
            'driver_id' => User::factory()->create(['role' => 'driver'])->id,
            'vehicle_id' => null,
            'origin' => 'Office',
            'destination' => 'City Hall',
            'purpose' => 'Official Business',
            'scheduled_departure' => now()->addDay(),
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/trips?status=pending')
            ->assertOk()
            ->assertJsonCount(0, 'data.0.passengers');

        $trip->passengers()->create([
            'name' => 'Test Passenger',
            'designation' => 'Staff',
        ]);

        $this->getJson('/api/trips?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data.0.passengers');

        $this->getJson('/api/trips?status=approved')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_creating_a_trip_invalidates_cached_trip_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/trips?page=1&per_page=20')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Trip::create([
            'driver_id' => User::factory()->create(['role' => 'driver'])->id,
            'vehicle_id' => null,
            'origin' => 'Office',
            'destination' => 'Regional Office',
            'purpose' => 'Inspection',
            'scheduled_departure' => now()->addDay(),
            'status' => 'pending',
        ]);

        $this->getJson('/api/trips?page=1&per_page=20')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_updating_and_deleting_a_trip_invalidates_filtered_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $trip = Trip::create([
            'driver_id' => User::factory()->create(['role' => 'driver'])->id,
            'vehicle_id' => null,
            'origin' => 'Office',
            'destination' => 'Regional Office',
            'purpose' => 'Inspection',
            'scheduled_departure' => now()->addDay(),
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/trips?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $trip->update(['status' => 'approved']);

        $this->getJson('/api/trips?status=pending')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson('/api/trips?status=approved')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $trip->delete();

        $this->getJson('/api/trips?status=approved')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
