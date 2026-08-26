<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the real HTTP route/controller layer rather than calling the
 * SearchVideos Action directly - the Action tests wouldn't have caught the
 * controller reading $request->query (Symfony's query-string InputBag, always
 * empty for a JSON POST body) instead of $request->input('query').
 */
class SearchControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_json_body_query_is_accepted_over_http(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/search/videos', ['query' => 'forklift']);

        $response->assertOk();
        $response->assertJsonPath('candidates_searched', 0);
    }
}
