<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->create(['password' => 'CurrentPass123']);
        Sanctum::actingAs($user, ['*']);

        $this->putJson('/api/change-password', [
            'current_password' => 'CurrentPass123',
            'new_password' => 'NewPass123',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(password_verify('NewPass123', $user->fresh()->password));
    }

    public function test_password_change_rejects_an_incorrect_current_password(): void
    {
        $user = User::factory()->create(['password' => 'CurrentPass123']);
        Sanctum::actingAs($user, ['*']);

        $this->putJson('/api/change-password', [
            'current_password' => 'WrongPass123',
            'new_password' => 'NewPass123',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The current password is incorrect.');
    }

    public function test_driver_trip_submission_creates_admin_notifications_and_admin_can_manage_them(): void
    {
        $admin = User::factory()->create(['name' => 'Admin One', 'role' => 'admin']);
        $driver = User::factory()->create(['name' => 'Driver One', 'role' => 'driver']);

        Sanctum::actingAs($driver, ['*']);

        $response = $this->postJson('/api/trips', [
            'origin' => 'Office',
            'destination' => 'City Hall',
            'purpose' => 'Official Business',
            'scheduled_departure' => now()->addDay()->toDateString(),
            'passengers' => [
                ['name' => 'Jane Doe', 'designation' => 'Staff'],
            ],
        ]);

        $response->assertCreated();

        $trip = Trip::latest()->first();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'trip_id' => $trip->id,
            'message' => 'New trip request from Driver One to City Hall',
            'is_read' => false,
        ]);

        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonFragment(['message' => 'New trip request from Driver One to City Hall']);

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 1);

        $notification = Notification::query()->where('user_id', $admin->id)->first();

        $this->putJson('/api/notifications/' . $notification->id . '/read')
            ->assertOk()
            ->assertJsonPath('message', 'Notification marked as read');

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }
}
