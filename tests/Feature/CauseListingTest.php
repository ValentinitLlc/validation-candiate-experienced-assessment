<?php

namespace Tests\Feature;

use App\Models\Cause;
use App\Models\Pledge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CauseListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_cause_listing_ordering_and_aggregates(): void
    {
        $olderFeatured = Cause::factory()->create(['is_featured' => true, 'created_at' => now()->subDays(2)]);
        $newerFeatured = Cause::factory()->create(['is_featured' => true, 'created_at' => now()->subDay()]);
        $normalCause = Cause::factory()->create(['is_featured' => false]);

        Pledge::factory()->create(['cause_id' => $newerFeatured->id, 'amount' => 150.00]);
        Pledge::factory()->create(['cause_id' => $newerFeatured->id, 'amount' => 50.00]);

        $response = $this->getJson('/api/causes');

        Accessibility:
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['id', 'title', 'total_pledged', 'pledge_count', 'is_featured', 'created_at']
                     ],
                     'links', 'meta'
                 ]);

        // Vérification de l'ordre : Featured Newest -> Featured Older -> Non-featured
        $data = $response->json('data');
        $this->assertEquals($newerFeatured->id, $data[0]['id']);
        $this->assertEquals($olderFeatured->id, $data[1]['id']);
        $this->assertEquals($normalCause->id, $data[2]['id']);

        // Vérification des agrégats pour newerFeatured
        $this->assertEquals(200.00, $data[0]['total_pledged']);
        $this->assertEquals(2, $data[0]['pledge_count']);
    }

    public function test_listing_does_not_produce_n_plus_one_queries(): void
    {
        Cause::factory()->count(5)->hasPledges(3)->create();

        DB::enableQueryLog();

        $this->getJson('/api/causes')->assertStatus(200);

        $queryCount = count(DB::getQueryLog());

        // Le nombre de requêtes doit être constant (ex: 2 ou 3 requêtes max : select causes + aggregates + count pagination)
        $this->assertLessThanOrEqual(4, $queryCount);
    }
}