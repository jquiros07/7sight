<?php

namespace Tests\Feature\Actions\Workspace;

use App\Actions\Workspace\ListWorkspaces;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListWorkspacesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_only_lists_workspaces_the_user_belongs_to(): void
    {
        $user = User::factory()->create();
        $ownWorkspace = Workspace::factory()->create();
        $ownWorkspace->users()->attach($user->id, ['role' => 'owner']);
        Workspace::factory()->create();

        $results = (new ListWorkspaces)($user);

        $this->assertCount(1, $results);
        $this->assertSame($ownWorkspace->id, $results->first()->id);
    }

    public function test_it_filters_by_search_term(): void
    {
        $user = User::factory()->create();
        $matching = Workspace::factory()->create(['name' => 'Downtown Cameras']);
        $matching->users()->attach($user->id, ['role' => 'owner']);
        $nonMatching = Workspace::factory()->create(['name' => 'Warehouse']);
        $nonMatching->users()->attach($user->id, ['role' => 'owner']);

        $results = (new ListWorkspaces)($user, ['search' => 'Downtown']);

        $this->assertCount(1, $results);
        $this->assertSame($matching->id, $results->first()->id);
    }

    public function test_it_sorts_by_the_requested_column_and_direction(): void
    {
        $user = User::factory()->create();
        $b = Workspace::factory()->create(['name' => 'B Workspace']);
        $b->users()->attach($user->id, ['role' => 'owner']);
        $a = Workspace::factory()->create(['name' => 'A Workspace']);
        $a->users()->attach($user->id, ['role' => 'owner']);

        $results = (new ListWorkspaces)($user, ['sort' => 'name', 'direction' => 'asc']);

        $this->assertSame(['A Workspace', 'B Workspace'], $results->pluck('name')->all());
    }

    public function test_it_caps_the_per_page_at_100(): void
    {
        $user = User::factory()->create();
        Workspace::factory()->count(3)->create()->each(
            fn (Workspace $workspace) => $workspace->users()->attach($user->id, ['role' => 'owner'])
        );

        $results = (new ListWorkspaces)($user, ['per_page' => 500]);

        $this->assertSame(100, $results->perPage());
    }
}
