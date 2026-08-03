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
}
