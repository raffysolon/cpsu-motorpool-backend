<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SavedDestinationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_list_and_edit_a_campus_pin(): void
    {
        $url = '/api/saved-destinations';
        $pin = ['name' => 'CPSU San Carlos', 'latitude' => 10.5, 'longitude' => 123.4];

        Sanctum::actingAs(User::factory()->create(['role' => 'driver']));
        $this->postJson($url, $pin)->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $id = $this->postJson($url, $pin)->assertCreated()->json('id');
        $this->getJson($url)->assertOk()->assertJsonPath('0.name', 'CPSU San Carlos');
        $this->postJson($url, $pin)->assertUnprocessable();
        $this->putJson("$url/$id", array_replace($pin, ['latitude' => 10.6]))->assertNoContent();
        $this->assertDatabaseHas('saved_destinations', ['id' => $id, 'latitude' => 10.6]);
    }
}
