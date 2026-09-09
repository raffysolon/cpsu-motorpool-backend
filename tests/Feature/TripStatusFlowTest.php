<?php

namespace Tests\Feature;

use App\Models\CoordinatorAssignment;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripStatusFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_fetch_only_pending_trips(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        Trip::create([
            'driver_id' => $driver->id,
            'vehicle_id' => null,
            'origin' => 'Office',
            'destination' => 'City Hall',
            'purpose' => 'Official Business',
            'scheduled_departure' => now()->addDay(),
            'status' => 'pending',
        ]);

        Trip::create([
            'driver_id' => $driver->id,
            'vehicle_id' => null,
            'origin' => 'Office',
            'destination' => 'Provincial Office',
            'purpose' => 'Fieldwork',
            'scheduled_departure' => now()->addDays(2),
            'status' => 'approved',
        ]);

        Sanctum::actingAs($driver, ['*']);

        $response = $this->getJson('/api/my-trips?status=pending');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.status', 'pending');
    }

    public function test_driver_can_update_departure_details_on_existing_trip(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $trip = Trip::create([
            'driver_id' => $driver->id,
            'vehicle_id' => null,
            'origin' => 'Old Origin',
            'destination' => 'City Hall',
            'purpose' => 'Official Business',
            'scheduled_departure' => '2026-09-03 07:30:00',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($driver, ['*']);

        $response = $this->putJson('/api/trips/' . $trip->id, [
            'departure_date' => '2026-09-03',
            'departure_time' => '08:15',
            'departure_place' => 'CPSU San Carlos',
        ]);

        $response->assertOk()
            ->assertJsonPath('trip.origin', 'CPSU San Carlos')
            ->assertJsonPath('trip.scheduled_departure', '2026-09-03 08:15:00');

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'origin' => 'CPSU San Carlos',
            'scheduled_departure' => '2026-09-03 08:15:00',
        ]);

        $this->getJson('/api/trips/' . $trip->id)
            ->assertOk()
            ->assertJsonPath('origin', 'CPSU San Carlos')
            ->assertJsonPath('scheduled_departure', '2026-09-03 08:15:00');
    }

    public function test_admin_approval_keeps_trips_scheduled_until_driver_starts(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);

        $futureTrip = Trip::create([
            'driver_id' => $driver->id,
            'vehicle_id' => null,
            'origin' => 'Office',
            'destination' => 'Regional Office',
            'purpose' => 'Inspection',
            'scheduled_departure' => now()->addDay(),
            'status' => 'pending',
        ]);

        $todayTrip = Trip::create([
            'driver_id' => $driver->id,
            'vehicle_id' => null,
            'origin' => 'Office',
            'destination' => 'Municipal Hall',
            'purpose' => 'Field Check',
            'scheduled_departure' => now()->startOfDay(),
            'status' => 'pending',
        ]);

        Sanctum::actingAs($driver, ['*']);

        $futureApprovalResponse = $this->putJson('/api/trips/' . $futureTrip->id . '/approve');
        $futureApprovalResponse->assertOk()
            ->assertJsonPath('trip.status', 'approved');

        $todayApprovalResponse = $this->putJson('/api/trips/' . $todayTrip->id . '/approve');
        $todayApprovalResponse->assertOk()
            ->assertJsonPath('trip.status', 'approved');

        $approvedTripsResponse = $this->getJson('/api/my-trips?status=approved');
        $approvedTripsResponse->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['effective_status' => 'scheduled']);
        $scheduledTripsResponse = $this->getJson('/api/my-trips?status=scheduled');
        $scheduledTripsResponse->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['effective_status' => 'scheduled']);

        $activeTripsResponse = $this->getJson('/api/my-trips?status=active');
        $activeTripsResponse->assertOk()->assertJsonCount(0);
    }

    public function test_driver_can_start_within_early_window_or_late_today_but_not_before_window_or_on_another_date(): void
    {
        $now = Carbon::parse('2026-09-06 10:00:00');
        Carbon::setTestNow($now);
        $driver = User::factory()->create(['role' => 'driver']);

        $createTrip = function (Carbon $scheduled) use ($driver): Trip {
            $trip = Trip::create([
                'driver_id' => $driver->id,
                'vehicle_id' => null,
                'origin' => 'Office',
                'destination' => 'City Hall',
                'purpose' => 'Official Business',
                'scheduled_departure' => $scheduled,
                'status' => 'approved',
            ]);

            $trip->movements()->create([
                'movement_no' => 1,
                'origin' => $trip->origin,
                'destination' => $trip->destination,
                'scheduled_departure' => $scheduled,
                'status' => 'scheduled',
            ]);

            return $trip;
        };

        $lateToday = $createTrip($now->copy()->subHour());
        $earlyWindow = $createTrip($now->copy()->addMinutes(5));
        $tooEarlyToday = $createTrip($now->copy()->addMinutes(11));
        $yesterday = $createTrip($now->copy()->subDay());

        Sanctum::actingAs($driver, ['*']);

        $this->postJson('/api/trips/' . $lateToday->id . '/start')
            ->assertOk();
        $this->postJson('/api/trips/' . $earlyWindow->id . '/start')
            ->assertOk()
            ->assertJsonPath('start_timing', 'early');
        $this->postJson('/api/trips/' . $tooEarlyToday->id . '/start')
            ->assertStatus(422);
        $this->postJson('/api/trips/' . $yesterday->id . '/start')
            ->assertStatus(422);

        Carbon::setTestNow();
    }

    public function test_driver_can_print_approved_trip_but_not_other_drivers_trip(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $otherDriver = User::factory()->create(['role' => 'driver']);

        $pendingTrip = Trip::create([
            'driver_id' => $driver->id,
            'vehicle_id' => null,
            'origin' => 'Office',
            'destination' => 'City Hall',
            'purpose' => 'Official Business',
            'scheduled_departure' => now()->addDay(),
            'status' => 'pending',
        ]);

        $approvedTrip = Trip::create([
            'driver_id' => $driver->id,
            'vehicle_id' => null,
            'origin' => 'Office',
            'destination' => 'City Hall',
            'purpose' => 'Official Business',
            'scheduled_departure' => now()->addDay(),
            'status' => 'approved',
        ]);

        $otherTrip = Trip::create([
            'driver_id' => $otherDriver->id,
            'vehicle_id' => null,
            'origin' => 'Office',
            'destination' => 'Provincial Office',
            'purpose' => 'Fieldwork',
            'scheduled_departure' => now()->addDays(2),
            'status' => 'approved',
        ]);

        Sanctum::actingAs($driver, ['*']);

        $pendingResponse = $this->getJson('/api/trips/' . $pendingTrip->id . '/print');
        $pendingResponse->assertForbidden();

        $pdfResponse = $this->getJson('/api/trips/' . $approvedTrip->id . '/print');
        $pdfResponse->assertOk()
            ->assertJsonPath('message', 'PDF generated successfully');

        $forbiddenResponse = $this->getJson('/api/trips/' . $otherTrip->id . '/print');
        $forbiddenResponse->assertForbidden();
    }

    public function test_available_driver_and_vehicle_lists_exclude_coordinator_assigned_and_conflicting_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver']);
        $otherDriver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::create(['name' => 'Van 1', 'plate_no' => 'ABC-1234', 'status' => 'active']);
        $otherVehicle = Vehicle::create(['name' => 'Van 2', 'plate_no' => 'XYZ-9876', 'status' => 'active']);
        $freeDriver = User::factory()->create(['role' => 'driver']);
        $freeVehicle = Vehicle::create(['name' => 'Van 3', 'plate_no' => 'LMN-9999', 'status' => 'active']);

        CoordinatorAssignment::create([
            'campus_name' => 'Main Campus',
            'coordinator_name' => 'Coordinator',
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ]);

        CoordinatorAssignment::create([
            'campus_name' => 'Main Campus',
            'coordinator_name' => 'Coordinator',
            'driver_id' => $otherDriver->id,
            'vehicle_id' => $otherVehicle->id,
        ]);

        $scheduled = now()->addDays(2)->setTime(9, 0, 0);

        Trip::create([
            'driver_id' => $freeDriver->id,
            'vehicle_id' => $freeVehicle->id,
            'origin' => 'Office',
            'destination' => 'City Hall',
            'purpose' => 'Inspection',
            'scheduled_departure' => $scheduled,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($admin, ['*']);

        $availableDriversResponse = $this->getJson('/api/available-drivers?scheduled_departure=' . urlencode($scheduled->format('Y-m-d H:i:s')));
        $availableDriversResponse->assertOk();
        $this->assertNotContains($driver->id, array_column($availableDriversResponse->json(), 'id'));
        $this->assertNotContains($otherDriver->id, array_column($availableDriversResponse->json(), 'id'));
        $this->assertNotContains($freeDriver->id, array_column($availableDriversResponse->json(), 'id'));

        $availableVehiclesResponse = $this->getJson('/api/available-vehicles?scheduled_departure=' . urlencode($scheduled->format('Y-m-d H:i:s')));
        $availableVehiclesResponse->assertOk();
        $this->assertNotContains($vehicle->id, array_column($availableVehiclesResponse->json(), 'id'));
        $this->assertNotContains($otherVehicle->id, array_column($availableVehiclesResponse->json(), 'id'));
        $this->assertNotContains($freeVehicle->id, array_column($availableVehiclesResponse->json(), 'id'));
    }

    public function test_admin_store_rejects_conflicting_driver_or_vehicle_assignment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::create(['name' => 'Van 1', 'plate_no' => 'ABC-1234', 'status' => 'active']);
        $assignment = CoordinatorAssignment::create([
            'campus_name' => 'Main Campus',
            'coordinator_name' => 'Coordinator',
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $scheduled = now()->addDays(2)->setTime(9, 0, 0);

        Trip::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'origin' => 'Office',
            'destination' => 'City Hall',
            'purpose' => 'Inspection',
            'scheduled_departure' => $scheduled,
            'status' => 'approved',
        ]);

        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/trips/admin-create', [
            'origin' => 'Office',
            'destination' => 'Provincial Office',
            'purpose' => 'Fieldwork',
            'scheduled_departure' => $scheduled->format('Y-m-d H:i:s'),
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'passengers' => [],
        ])->assertStatus(422)
            ->assertJsonPath('message', 'This driver or vehicle is already assigned to a conflicting trip during the selected schedule.');
    }

    public function test_pending_trip_does_not_block_vehicle_availability_for_the_same_schedule(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::create(['name' => 'Van 1', 'plate_no' => 'ABC-1234', 'status' => 'active']);

        $scheduled = now()->addDays(2)->setTime(9, 0, 0);

        Trip::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'origin' => 'Office',
            'destination' => 'City Hall',
            'purpose' => 'Draft request',
            'scheduled_departure' => $scheduled,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson('/api/available-vehicles?scheduled_departure=' . urlencode($scheduled->format('Y-m-d H:i:s')));
        $response->assertOk();
        $this->assertContains($vehicle->id, array_column($response->json(), 'id'));
    }

    public function test_admin_can_create_trip_with_unassigned_driver_and_vehicle(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::create(['name' => 'Van 4', 'plate_no' => 'QRS-4444', 'status' => 'active']);
        $scheduled = now()->addDays(1)->setTime(10, 30, 0);

        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/trips/admin-create', [
            'origin' => 'Office',
            'destination' => 'Regional Office',
            'purpose' => 'Inspection',
            'scheduled_departure' => $scheduled->format('Y-m-d H:i:s'),
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'passengers' => [],
        ])->assertStatus(201)
            ->assertJsonPath('trip.driver_id', $driver->id)
            ->assertJsonPath('trip.vehicle_id', $vehicle->id);
    }

    public function test_admin_can_create_trip_after_selecting_driver_and_vehicle_before_filling_trip_details(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver']);
        $vehicle = Vehicle::create(['name' => 'Van 1', 'plate_no' => 'ABC-1234', 'status' => 'active']);

        CoordinatorAssignment::create([
            'campus_name' => 'Main Campus',
            'coordinator_name' => 'Coordinator',
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ]);

        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/trips/admin-create', [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'origin' => '',
            'destination' => '',
            'purpose' => '',
            'scheduled_departure' => null,
            'passengers' => [],
        ])->assertCreated()
            ->assertJsonPath('trip.driver_id', $driver->id)
            ->assertJsonPath('trip.vehicle_id', $vehicle->id);
    }

    public function test_admin_can_preview_trip_ticket_for_pending_trip(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'driver']);

        $trip = Trip::create([
            'driver_id' => $driver->id,
            'vehicle_id' => null,
            'origin' => 'Office',
            'destination' => 'City Hall',
            'purpose' => 'Official Business',
            'scheduled_departure' => now()->addDay(),
            'status' => 'pending',
        ]);

        $trip->passengers()->create(['name' => 'Juan Dela Cruz']);

        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/trips/' . $trip->id . '/print')
            ->assertOk()
            ->assertJsonPath('message', 'PDF generated successfully');
    }
}
