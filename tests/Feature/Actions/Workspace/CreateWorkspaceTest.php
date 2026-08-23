<?php

namespace Tests\Feature\Actions\Workspace;

use App\Actions\Workspace\CreateWorkspace;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_workspace_owned_by_the_given_user(): void
    {
        $user = User::factory()->create();

        $workspace = (new CreateWorkspace)($user, [
            'name' => 'Acme Security',
            'description' => 'Loss prevention cameras',
        ]);

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'owner_id' => $user->id,
            'name' => 'Acme Security',
            'slug' => 'acme-security',
            'description' => 'Loss prevention cameras',
        ]);
    }

    public function test_it_attaches_the_creating_user_as_owner(): void
    {
        $user = User::factory()->create();

        $workspace = (new CreateWorkspace)($user, ['name' => 'Acme Security']);

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_it_deduplicates_the_slug_when_the_name_is_already_taken(): void
    {
        $user = User::factory()->create();
        Workspace::factory()->create(['name' => 'Acme Security', 'slug' => 'acme-security']);

        $workspace = (new CreateWorkspace)($user, ['name' => 'Acme Security']);

        $this->assertSame('acme-security-2', $workspace->slug);
    }

    public function test_it_requires_a_name(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);

        (new CreateWorkspace)($user, ['description' => 'Missing a name']);
    }
}
