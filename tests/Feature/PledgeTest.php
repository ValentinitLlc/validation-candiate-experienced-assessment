<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PledgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_pledge_creation_is_rate_limited(): void
    {
        $cause = \App\Models\Cause::factory()->create([
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
        $cause = \App\Models\Cause::factory()->create([
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
}