<?php

namespace Tests\Feature\Actions\Workspace;

use App\Actions\Workspace\UpdateWorkspace;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UpdateWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_update_the_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'name' => 'Old Name']);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');

        $updated = (new UpdateWorkspace)($owner, $workspace, ['name' => 'New Name']);

        $this->assertSame('New Name', $updated->name);
        $this->assertSame('new-name', $updated->slug);
    }

    public function test_an_admin_can_update_the_workspace(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $this->assignWorkspaceRole($workspace, $admin, 'admin');

        $updated = (new UpdateWorkspace)($admin, $workspace, ['description' => 'Updated by admin']);

        $this->assertSame('Updated by admin', $updated->description);
    }

    public function test_a_member_cannot_update_the_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');
        $this->assignWorkspaceRole($workspace, $member, 'member');

        try {
            (new UpdateWorkspace)($member, $workspace, ['name' => 'Hijacked']);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_an_outsider_cannot_update_the_workspace(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');

        try {
            (new UpdateWorkspace)($outsider, $workspace, ['name' => 'Hijacked']);
            $this->fail('Expected a 403 authorization exception.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_it_does_not_regenerate_the_slug_when_the_name_is_unchanged(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'name' => 'Acme', 'slug' => 'acme']);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');

        $updated = (new UpdateWorkspace)($owner, $workspace, ['name' => 'Acme', 'description' => 'New description']);

        $this->assertSame('acme', $updated->slug);
    }

    public function test_it_rejects_invalid_input(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $this->assignWorkspaceRole($workspace, $owner, 'owner');

        $this->expectException(ValidationException::class);

        (new UpdateWorkspace)($owner, $workspace, ['name' => '']);
    }
}
