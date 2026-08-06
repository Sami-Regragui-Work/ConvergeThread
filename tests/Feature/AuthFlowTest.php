<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use Database\Seeders\Permanents\OwnerSeeder;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
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

    public function test_banned_user_cannot_login(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $owner = User::where('tenant_id', 1)->first();
        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');

        User::factory()->create([
            'email' => 'banned@example.com',
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
            'banned_by_id' => $owner->id,
        ]);

        $response = $this->post(route('auth.login.store'), [
            'email' => 'banned@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertGuest();
    }

    public function test_register_assigns_member_role_and_redirects(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');

        $response = $this->post(route('auth.register.store'), [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_slug' => 'acme_corp',
            'display_name' => 'New User',
        ]);

        $response->assertRedirect(route('groups.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
        ]);
    }

    public function test_closed_tenant_user_cannot_login(): void
    {
        $owner = User::where('tenant_id', 1)->first();
        $tenant = Tenant::create([
            'slug' => 'closed_corp',
            'admin_email' => 'admin@closed.com',
            'closed_by_id' => $owner->id,
        ]);
        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');

        User::factory()->create([
            'email' => 'member@closed.com',
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
        ]);

        $response = $this->post(route('auth.login.store'), [
            'email' => 'member@closed.com',
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertGuest();
    }

    public function test_register_rejects_closed_tenant(): void
    {
        $owner = User::where('tenant_id', 1)->first();
        Tenant::create([
            'slug' => 'closed_corp',
            'admin_email' => 'admin@closed.com',
            'closed_by_id' => $owner->id,
        ]);

        $response = $this->post(route('auth.register.store'), [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_slug' => 'closed_corp',
            'display_name' => 'New User',
        ]);

        $response->assertRedirect();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'newuser@example.com']);
    }
}
