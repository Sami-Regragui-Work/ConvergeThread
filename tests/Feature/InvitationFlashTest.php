<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use Database\Seeders\permanents\OwnerSeeder;
use Database\Seeders\permanents\SystemTenantRoleSeeder;
use Database\Seeders\permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationFlashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SystemTenantRoleSeeder::class,
            SystemTenantSeeder::class,
            OwnerSeeder::class,
        ]);
    }

    public function test_member_invitation_flashes_copyable_link(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $response = $this->actingAs($admin)->post(route('invitations.tenant.store'), [
            'tenant_id' => $tenant->id,
            'email' => 'invitee@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('flash_links');

        $links = session('flash_links');
        $this->assertNotEmpty($links[0]['url'] ?? null);
        $this->assertStringContainsString('/invitations/', $links[0]['url']);
    }

    public function test_owner_admin_invitation_flashes_copyable_link(): void
    {
        $owner = User::where('tenant_id', 1)->first();

        $response = $this->actingAs($owner)->post(route('invitations.owner.store'), [
            'email' => 'newadmin@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('flash_links');
        $this->assertStringContainsString('/invitations/', session('flash_links')[0]['url']);
    }
}
