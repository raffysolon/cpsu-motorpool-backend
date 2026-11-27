<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_can_read_and_dismiss_its_own_alert_without_losing_event_history(): void
    {
        foreach (['admin', 'driver'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $notification = Notification::create([
                'user_id' => $user->id, 'message' => 'New trip update',
                'is_read' => false, 'event_key' => 'test-'.$role,
            ]);
            Sanctum::actingAs($user);
            $this->getJson('/api/notifications')->assertOk()->assertJsonCount(1);
            $this->getJson('/api/notifications/unread-count')->assertJsonPath('count', 1);
            $this->putJson('/api/notifications/'.$notification->id.'/read')->assertOk();
            $this->getJson('/api/notifications/unread-count')->assertJsonPath('count', 0);
            $this->deleteJson('/api/notifications/'.$notification->id)->assertNoContent();
            $this->deleteJson('/api/notifications/'.$notification->id)->assertNoContent();
            $this->getJson('/api/notifications')->assertOk()->assertJsonCount(0);
            $this->assertNotNull($notification->fresh()->dismissed_at);
            $this->assertDatabaseHas('notifications', ['id' => $notification->id, 'event_key' => 'test-'.$role]);
        }
    }

    public function test_another_account_cannot_read_or_dismiss_a_notification(): void
    {
        $owner = User::factory()->create(['role' => 'driver']);
        $notification = Notification::create(['user_id' => $owner->id, 'message' => 'Private', 'is_read' => false]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->putJson('/api/notifications/'.$notification->id.'/read')->assertForbidden();
        $this->deleteJson('/api/notifications/'.$notification->id)->assertForbidden();
        $this->assertNull($notification->fresh()->dismissed_at);
        $this->assertFalse($notification->fresh()->is_read);
    }

    public function test_unauthenticated_delete_is_rejected(): void
    {
        $this->deleteJson('/api/notifications/1')->assertUnauthorized();
    }
}
