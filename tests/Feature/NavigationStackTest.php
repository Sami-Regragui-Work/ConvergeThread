<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\GroupService;
use App\Support\BackNavigation;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationStackTest extends TestCase
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

    public function test_back_url_follows_forward_navigation_and_truncates_on_return(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $group = app(GroupService::class)->create('Engineering', $admin);

        $this->actingAs($admin)->get(route('groups.index'));
        $this->actingAs($admin)->get(route('groups.show', $group));

        $membersUrl = route('groups.members.index', $group);
        $this->actingAs($admin)->get($membersUrl);

        $this->assertSame(
            route('groups.show', $group),
            BackNavigation::url(),
        );

        $this->actingAs($admin)->get(route('groups.show', $group));

        $this->assertSame(
            route('groups.index'),
            BackNavigation::url(),
        );

        $this->actingAs($admin)->get($membersUrl);

        $this->assertSame(
            route('groups.show', $group),
            BackNavigation::url(),
        );
    }
}
