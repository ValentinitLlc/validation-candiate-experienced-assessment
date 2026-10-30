<?php

namespace Tests\Feature;

use App\Models\Cause;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CauseTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_create_cause(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/causes', [
            'title' => 'New Fundraising Cause',
            'description' => 'Description of the cause',
            'goal_amount' => 5000.00,
            'is_featured' => true,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('data.title', 'New Fundraising Cause');

        $this->assertDatabaseHas('causes', ['title' => 'New Fundraising Cause']);
    }

    public function test_unauthorized_user_cannot_create_cause(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/admin/causes', [
            'title' => 'Unauthorized Cause',
            'description' => 'Description',
            'goal_amount' => 1000.00,
        ]);

        $response->assertForbidden();
    }

    public function test_authorized_admin_can_update_featured_status(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $cause = Cause::factory()->create(['is_featured' => false]);

        $response = $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/causes/{$cause->id}/featured", [
            'is_featured' => true,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('causes', ['id' => $cause->id, 'is_featured' => true]);
    }

    public function test_unauthorized_user_cannot_update_featured_status(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $cause = Cause::factory()->create(['is_featured' => false]);

        $response = $this->actingAs($user, 'sanctum')->patchJson("/api/admin/causes/{$cause->id}/featured", [
            'is_featured' => true,
        ]);

        $response->assertForbidden();
    }
}