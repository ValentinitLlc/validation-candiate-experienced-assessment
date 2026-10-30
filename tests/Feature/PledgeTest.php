<?php

namespace Tests\Feature;

use App\Events\CauseGoalReached;
use App\Models\Cause;
use App\Notifications\CauseGoalReachedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_pledge_creation_is_rate_limited(): void
    {
        $cause = Cause::factory()->create([
            'is_active' => true, 
            'goal_amount' => 1000
        ]);

        $payload = [
            'cause_id' => $cause->id,
            'pledger_name' => 'John Doe',
            'pledger_email' => 'john@example.com',
            'amount' => 50,
        ];

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/pledges', $payload);
            $response->assertStatus(201);
        }

        $response = $this->postJson('/api/pledges', $payload);
        $response->assertStatus(429);
    }

    public function test_pledge_submission_is_idempotent(): void
    {
        $cause = Cause::factory()->create([
            'is_active' => true,
            'goal_amount' => 1000
        ]);

        $payload = [
            'cause_id' => $cause->id,
            'pledger_name' => 'Jane Doe',
            'pledger_email' => 'jane@example.com',
            'amount' => 100,
        ];

        $idempotencyKey = 'test-uuid-key-12345';

        $response1 = $this->withHeader('X-Idempotency-Key', $idempotencyKey)
                          ->postJson('/api/pledges', $payload);

        $response1->assertStatus(201);

        $response2 = $this->withHeader('X-Idempotency-Key', $idempotencyKey)
                          ->postJson('/api/pledges', $payload);

        $response2->assertStatus(201);

        $this->assertDatabaseCount('pledges', 1);
    }

    public function test_pledge_validation_rules(): void
    {
        $cause = Cause::factory()->create(['is_active' => true]);

        // Montant zéro ou négatif rejeté
        $this->postJson('/api/pledges', ['cause_id' => $cause->id, 'pledger_name' => 'Test', 'pledger_email' => 'test@example.com', 'amount' => 0])
             ->assertStatus(422);

        $this->postJson('/api/pledges', ['cause_id' => $cause->id, 'pledger_name' => 'Test', 'pledger_email' => 'test@example.com', 'amount' => -10])
             ->assertStatus(422);

        // Email invalide rejeté
        $this->postJson('/api/pledges', ['cause_id' => $cause->id, 'pledger_name' => 'Test', 'pledger_email' => 'invalid-email', 'amount' => 50])
             ->assertStatus(422);
    }

    public function test_pledge_cannot_be_created_for_inactive_cause(): void
    {
        $cause = Cause::factory()->create(['is_active' => false]);

        $response = $this->postJson('/api/pledges', [
            'cause_id' => $cause->id,
            'pledger_name' => 'John Doe',
            'pledger_email' => 'john@example.com',
            'amount' => 50.00,
        ]);

        $response->assertStatus(422);
    }

    public function test_pledge_reaching_goal_deactivates_cause_and_dispatches_events(): void
    {
        Event::fake([CauseGoalReached::class]);
        Notification::fake();

        $cause = Cause::factory()->create(['is_active' => true, 'goal_amount' => 100.00]);

        $response = $this->postJson('/api/pledges', [
            'cause_id' => $cause->id,
            'pledger_name' => 'Jane Doe',
            'pledger_email' => 'jane@example.com',
            'amount' => 100.00,
        ]);

        $admin = \App\Models\User::factory()->create(['is_admin' => true]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('causes', ['id' => $cause->id, 'is_active' => false]);

        Event::assertDispatched(CauseGoalReached::class);
        Notification::assertNothingSent();
    }
}