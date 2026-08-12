<?php

namespace Tests\Feature;

use App\Models\RoleHierarchy;
use App\Models\RoleHierarchyLevel;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\GroupService;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HierarchyNodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SystemTenantRoleSeeder::class,
            SystemTenantSeeder::class,
        ]);
    }

    private function makeTenant(): array
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'founder@acme.com']);

        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $founder = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
            'email' => 'founder@acme.com',
        ]);

        return [$tenant, $founder];
    }

    public function test_store_creates_member_hierarchy_with_top_node(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $this->actingAs($founder)->post(route('hierarchies.store'), [
            'name' => 'Engineering',
            'kind' => 'member',
        ], ['Accept' => 'application/json'])->assertOk();

        $hierarchy = RoleHierarchy::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('member', $hierarchy->kind);

        $top = $hierarchy->levels->first();
        $this->assertSame(0, $top->level);
        $this->assertNull($top->parent_id);
    }

    public function test_store_defaults_to_member_kind(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $this->actingAs($founder)->post(route('hierarchies.store'), ['name' => 'Ops']);

        $this->assertSame('member', RoleHierarchy::where('tenant_id', $tenant->id)->firstOrFail()->kind);
    }

    public function test_store_creates_role_hierarchy(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $this->actingAs($founder)->post(route('hierarchies.store'), [
            'name' => 'Role tree',
            'kind' => 'role',
        ]);

        $hierarchy = RoleHierarchy::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('role', $hierarchy->kind);
        $this->assertCount(1, $hierarchy->levels);
    }

    public function test_add_node_under_parent_and_unlinked_top_level(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $hierarchy = RoleHierarchy::create(['tenant_id' => $tenant->id, 'name' => 'Eng']);
        $top = $hierarchy->levels()->create([
            'level' => 0,
            'label' => 'Top level',
            'kind' => 'member',
        ]);

        $this->actingAs($founder)->post(route('hierarchies.levels.store', $hierarchy), [
            'parent_id' => $top->id,
            'kind' => 'member',
        ])->assertRedirect();

        $this->actingAs($founder)->post(route('hierarchies.levels.store', $hierarchy), [
            'kind' => 'member',
        ])->assertRedirect();

        $this->assertSame(3, $hierarchy->levels()->count());

        $child = $hierarchy->levels()->where('parent_id', $top->id)->firstOrFail();
        $this->assertSame(1, $child->level);
        $this->assertSame('Level 1', $child->label);

        $topLevel = $hierarchy->levels()->whereNull('parent_id')->where('id', '!=', $top->id)->firstOrFail();
        $this->assertSame(0, $topLevel->level);
        $this->assertSame('Top level', $topLevel->label);
    }

    public function test_role_hierarchy_rejects_member_and_group_nodes(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $hierarchy = RoleHierarchy::create(['tenant_id' => $tenant->id, 'name' => 'Roles', 'kind' => 'role']);

        $this->actingAs($founder)->post(route('hierarchies.levels.store', $hierarchy), [
            'kind' => 'member',
        ])->assertStatus(422);

        $this->assertSame(0, $hierarchy->levels()->count());
    }

    public function test_member_node_rejects_user_already_on_vertical_path(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $member = User::factory()->create(['tenant_id' => $tenant->id]);

        $hierarchy = RoleHierarchy::create(['tenant_id' => $tenant->id, 'name' => 'Eng']);
        $top = $hierarchy->levels()->create(['level' => 0, 'label' => 'Top level', 'kind' => 'member']);
        $sub = $hierarchy->levels()->create([
            'parent_id' => $top->id,
            'level' => 1,
            'label' => 'Level 1',
            'kind' => 'member',
        ]);

        $top->members()->attach($member);

        $this->actingAs($founder)->patch(route('hierarchies.levels.members', $sub), [
            'user_ids' => [$member->id],
        ])->assertSessionHasErrors('hierarchies');

        $this->assertCount(0, $sub->members);
    }

    public function test_same_member_allowed_in_unrelated_branch(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $member = User::factory()->create(['tenant_id' => $tenant->id]);

        $hierarchy = RoleHierarchy::create(['tenant_id' => $tenant->id, 'name' => 'Eng']);
        $top = $hierarchy->levels()->create(['level' => 0, 'label' => 'Top level', 'kind' => 'member']);
        $branchA = $hierarchy->levels()->create(['parent_id' => $top->id, 'level' => 1, 'label' => 'A', 'kind' => 'member']);
        $branchB = $hierarchy->levels()->create(['parent_id' => $top->id, 'level' => 1, 'label' => 'B', 'kind' => 'member']);

        $branchA->members()->attach($member);

        $this->actingAs($founder)->patch(route('hierarchies.levels.members', $branchB), [
            'user_ids' => [$member->id],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($branchB->members()->where('users.id', $member->id)->exists());
    }

    public function test_link_rejects_circular_parent(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $hierarchy = RoleHierarchy::create(['tenant_id' => $tenant->id, 'name' => 'Eng']);
        $top = $hierarchy->levels()->create(['level' => 0, 'label' => 'Top level', 'kind' => 'member']);
        $topLevel = $hierarchy->levels()->create(['level' => 0, 'label' => 'Top level', 'kind' => 'member']);

        $this->actingAs($founder)->patch(route('hierarchies.levels.link', $topLevel), [
            'parent_id' => $top->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($founder)->patch(route('hierarchies.levels.link', $top), [
            'parent_id' => $topLevel->id,
        ])->assertSessionHasErrors('hierarchies');

        $this->assertSame($top->id, $topLevel->fresh()->parent_id);
    }

    public function test_link_rejects_contradiction_across_subtree(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $member = User::factory()->create(['tenant_id' => $tenant->id]);

        $hierarchy = RoleHierarchy::create(['tenant_id' => $tenant->id, 'name' => 'Eng']);
        $top = $hierarchy->levels()->create(['level' => 0, 'label' => 'Top level', 'kind' => 'member']);
        $topLevel = $hierarchy->levels()->create(['level' => 0, 'label' => 'Top level', 'kind' => 'member']);

        $top->members()->attach($member);
        $topLevel->members()->attach($member);

        $this->actingAs($founder)->patch(route('hierarchies.levels.link', $topLevel), [
            'parent_id' => $top->id,
        ])->assertSessionHasErrors('hierarchies');

        $this->assertNull($topLevel->fresh()->parent_id);
    }

    public function test_group_node_attach(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $group = app(GroupService::class)->create('Engineering', $founder);

        $hierarchy = RoleHierarchy::create(['tenant_id' => $tenant->id, 'name' => 'Eng']);
        $node = $hierarchy->levels()->create(['level' => 0, 'label' => 'Top level', 'kind' => 'member']);

        $this->actingAs($founder)->patch(route('hierarchies.levels.group', $node), [
            'group_id' => $group->id,
        ])->assertSessionHasNoErrors();

        $node = $node->fresh();
        $this->assertSame('group', $node->kind);
        $this->assertSame($group->id, $node->group_id);
    }

    public function test_group_node_in_role_hierarchy_is_rejected(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $group = app(GroupService::class)->create('Engineering', $founder);

        $hierarchy = RoleHierarchy::create(['tenant_id' => $tenant->id, 'name' => 'Roles', 'kind' => 'role']);
        $node = $hierarchy->levels()->create(['level' => 0, 'label' => 'Top level', 'kind' => 'role']);

        $this->actingAs($founder)->patch(route('hierarchies.levels.group', $node), [
            'group_id' => $group->id,
        ])->assertStatus(422);
    }

    public function test_role_node_attach_and_role_path_conflict(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $adminRole = TenantRole::where('is_system', true)->where('name', 'Admin')->firstOrFail();
        $memberRole = TenantRole::where('is_system', true)->where('name', 'Member')->firstOrFail();

        $hierarchy = RoleHierarchy::create(['tenant_id' => $tenant->id, 'name' => 'Roles', 'kind' => 'role']);
        $top = $hierarchy->levels()->create(['level' => 0, 'label' => 'Top level', 'kind' => 'role']);
        $child = $hierarchy->levels()->create(['parent_id' => $top->id, 'level' => 1, 'label' => 'Level 1', 'kind' => 'role']);

        $top->update(['role_id' => $adminRole->id]);

        $this->actingAs($founder)->patch(route('hierarchies.levels.role', $child), [
            'role_id' => $adminRole->id,
        ])->assertSessionHasErrors('hierarchies');

        $this->actingAs($founder)->patch(route('hierarchies.levels.role', $child), [
            'role_id' => $memberRole->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($memberRole->id, $child->fresh()->role_id);
    }

    public function test_destroy_level_unlinks_children(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $hierarchy = RoleHierarchy::create(['tenant_id' => $tenant->id, 'name' => 'Eng']);
        $top = $hierarchy->levels()->create(['level' => 0, 'label' => 'Top level', 'kind' => 'member']);
        $child = $hierarchy->levels()->create(['parent_id' => $top->id, 'level' => 1, 'label' => 'Child', 'kind' => 'member']);

        $this->actingAs($founder)->delete(route('hierarchies.levels.destroy', $top))->assertRedirect();

        $this->assertNull($child->fresh()->parent_id);
        $this->assertNull(RoleHierarchyLevel::find($top->id));
    }

    public function test_add_parent_inserts_new_level_above_and_shifts_depth(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $hierarchy = RoleHierarchy::create(['tenant_id' => $tenant->id, 'name' => 'Eng']);
        $top = $hierarchy->levels()->create(['level' => 0, 'label' => 'Top level', 'kind' => 'member']);
        $child = $hierarchy->levels()->create(['parent_id' => $top->id, 'level' => 1, 'label' => 'Level 1', 'kind' => 'member']);
        $grandchild = $hierarchy->levels()->create(['parent_id' => $child->id, 'level' => 2, 'label' => 'Level 2', 'kind' => 'member']);

        $this->actingAs($founder)->patch(route('hierarchies.levels.add-parent', $top))->assertRedirect();

        $newParent = RoleHierarchyLevel::where('role_hierarchy_id', $hierarchy->id)->where('level', 0)->firstOrFail();
        $this->assertNull($newParent->parent_id);
        $this->assertSame('Top level', $newParent->label);

        $top = $top->fresh();
        $this->assertSame($newParent->id, $top->parent_id);
        $this->assertSame(1, $top->level);
        $this->assertSame('Level 1', $top->label);

        $this->assertSame(2, $child->fresh()->level);
        $this->assertSame(3, $grandchild->fresh()->level);
    }

    public function test_add_parent_in_role_hierarchy_creates_role_level(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $hierarchy = RoleHierarchy::create(['tenant_id' => $tenant->id, 'name' => 'Roles', 'kind' => 'role']);
        $top = $hierarchy->levels()->create(['level' => 0, 'label' => 'Top level', 'kind' => 'role']);

        $this->actingAs($founder)->patch(route('hierarchies.levels.add-parent', $top))->assertRedirect();

        $newParent = RoleHierarchyLevel::where('role_hierarchy_id', $hierarchy->id)->where('level', 0)->firstOrFail();
        $this->assertSame('role', $newParent->kind);
        $this->assertSame($newParent->id, $top->fresh()->parent_id);
    }

    public function test_label_follows_depth_after_linking(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $hierarchy = RoleHierarchy::create(['tenant_id' => $tenant->id, 'name' => 'Eng']);
        $top = $hierarchy->levels()->create(['level' => 0, 'kind' => 'member']);
        $topLevel = $hierarchy->levels()->create(['level' => 0, 'kind' => 'member']);

        $this->assertSame('Top level', $topLevel->label);

        $this->actingAs($founder)->patch(route('hierarchies.levels.link', $topLevel), [
            'parent_id' => $top->id,
        ])->assertSessionHasNoErrors();

        $topLevel = $topLevel->fresh();
        $this->assertSame(1, $topLevel->level);
        $this->assertSame('Level 1', $topLevel->label);
    }

    public function test_node_type_can_switch_freely_between_member_and_group(): void
    {
        [$tenant, $founder] = $this->makeTenant();

        $group = app(GroupService::class)->create('Engineering', $founder);

        $hierarchy = RoleHierarchy::create(['tenant_id' => $tenant->id, 'name' => 'Eng']);
        $node = $hierarchy->levels()->create(['level' => 0, 'kind' => 'member']);

        $this->actingAs($founder)->patch(route('hierarchies.levels.group', $node), [
            'group_id' => $group->id,
        ])->assertSessionHasNoErrors();

        $node = $node->fresh();
        $this->assertSame('group', $node->kind);
        $this->assertSame($group->id, $node->group_id);

        $this->actingAs($founder)->patch(route('hierarchies.levels.member', $node))->assertSessionHasNoErrors();

        $node = $node->fresh();
        $this->assertSame('member', $node->kind);
        $this->assertNull($node->group_id);

        $this->actingAs($founder)->patch(route('hierarchies.levels.group', $node), [
            'group_id' => $group->id,
        ])->assertSessionHasNoErrors();

        $node = $node->fresh();
        $this->assertSame('group', $node->kind);
        $this->assertSame($group->id, $node->group_id);
    }
}
