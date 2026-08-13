<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use App\Services\InvitationService;
use Database\Seeders\Permanents\OwnerSeeder;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
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

    public function test_invitation_created_at_is_not_in_the_future(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $this->actingAs($admin)->post(route('invitations.tenant.store'), [
            'tenant_id' => $tenant->id,
            'email' => 'invitee@example.com',
        ]);

        $invitation = Invitation::where('email', 'invitee@example.com')->firstOrFail();

        $this->assertFalse($invitation->created_at->isFuture());
        $this->assertTrue($invitation->created_at->lte(now()));
        $this->assertTrue($invitation->created_at->gte(now()->subMinutes(5)));
    }

    public function test_closed_invitation_rows_render_ago_not_from_now(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $invitation = app(InvitationService::class)->createMemberInvitation('invitee@example.com', $admin, $tenant);
        $invitation->update(['accepted_at' => now()]);

        $content = $this->actingAs($admin)
            ->get(route('invitations.manage.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('created', $content);
        $this->assertStringContainsString('ago', $content);
        $this->assertStringNotContainsString('from now', $content);
    }
}
