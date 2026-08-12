<?php

namespace Tests\Feature;

use App\Models\RegistrationRequest;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use Database\Seeders\Permanents\OwnerSeeder;
use Database\Seeders\Permanents\SystemTenantRoleSeeder;
use Database\Seeders\Permanents\SystemTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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

    public function test_register_creates_pending_request_and_notifies_approvers(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $admin = User::factory()->create([
            'email' => 'admin@acme.com',
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $response = $this->post(route('auth.register.store'), [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_slug' => 'acme_corp',
            'display_name' => 'New User',
        ]);

        $response->assertRedirect(route('auth.login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'newuser@example.com']);
        $this->assertDatabaseHas('registration_requests', [
            'email' => 'newuser@example.com',
            'tenant_id' => $tenant->id,
            'status' => 'pending',
        ]);

        $notification = $admin->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('registration_pending', $notification->data['type'] ?? null);
        $this->assertSame('newuser@example.com', $notification->data['email'] ?? null);
    }

    public function test_register_with_unknown_slug_notifies_owner(): void
    {
        $owner = User::where('tenant_id', 1)->first();

        $response = $this->post(route('auth.register.store'), [
            'email' => 'stranger@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_slug' => 'nonexistent_workspace',
            'display_name' => 'Strange Visitor',
        ]);

        $response->assertRedirect(route('auth.login'));

        $this->assertGuest();
        $this->assertDatabaseHas('registration_requests', [
            'email' => 'stranger@example.com',
            'tenant_id' => null,
            'tenant_slug' => 'nonexistent_workspace',
            'status' => 'pending',
        ]);

        $notification = $owner->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('registration_pending', $notification->data['type'] ?? null);
    }

    public function test_register_blocks_duplicate_pending_email(): void
    {
        Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);

        $this->post(route('auth.register.store'), [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_slug' => 'acme_corp',
        ]);

        $response = $this->post(route('auth.register.store'), [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_slug' => 'acme_corp',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, RegistrationRequest::where('email', 'newuser@example.com')->count());
    }

    public function test_workspace_admin_approves_registration_request(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $admin = User::factory()->create([
            'email' => 'admin@acme.com',
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $registration = RegistrationRequest::create([
            'email' => 'newuser@example.com',
            'password' => bcrypt('password123'),
            'tenant_slug' => 'acme_corp',
            'tenant_id' => $tenant->id,
            'display_name' => 'New User',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(
            route('workspace.registrations.approve', $registration),
            ['tenant_id' => $tenant->id],
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
        ]);

        $this->assertDatabaseHas('registration_requests', [
            'id' => $registration->id,
            'status' => 'approved',
        ]);

        $this->assertTrue(Auth::validate([
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ]));
    }

    public function test_member_cannot_approve_registration_request(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');
        $member = User::factory()->create([
            'email' => 'member@acme.com',
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
        ]);

        $registration = RegistrationRequest::create([
            'email' => 'newuser@example.com',
            'password' => bcrypt('password123'),
            'tenant_id' => $tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($member)->post(
            route('workspace.registrations.approve', $registration),
            ['tenant_id' => $tenant->id],
        );

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'newuser@example.com']);
    }

    public function test_owner_approves_unassigned_request_with_tenant_choice(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');
        $owner = User::where('tenant_id', 1)->first();

        $registration = RegistrationRequest::create([
            'email' => 'stranger@example.com',
            'password' => bcrypt('password123'),
            'tenant_slug' => 'nonexistent_workspace',
            'tenant_id' => null,
            'display_name' => 'Strange Visitor',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($owner)->post(
            route('owner.registrations.approve', $registration),
            ['tenant_id' => $tenant->id],
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'stranger@example.com',
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
        ]);

        $this->assertDatabaseHas('registration_requests', [
            'id' => $registration->id,
            'status' => 'approved',
        ]);
    }

    public function test_owner_can_approve_tenant_scoped_request(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $memberRoleId = TenantRole::where('is_system', true)->where('name', 'Member')->value('id');
        $owner = User::where('tenant_id', 1)->first();

        $registration = RegistrationRequest::create([
            'email' => 'newuser@example.com',
            'password' => bcrypt('password123'),
            'tenant_id' => $tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($owner)->post(
            route('owner.registrations.approve', $registration),
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $memberRoleId,
        ]);
    }

    public function test_registration_request_can_be_rejected(): void
    {
        $tenant = Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);
        $adminRoleId = TenantRole::where('is_system', true)->where('name', 'Admin')->value('id');
        $admin = User::factory()->create([
            'email' => 'admin@acme.com',
            'tenant_id' => $tenant->id,
            'tenant_role_id' => $adminRoleId,
        ]);

        $registration = RegistrationRequest::create([
            'email' => 'newuser@example.com',
            'password' => bcrypt('password123'),
            'tenant_id' => $tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(
            route('workspace.registrations.reject', $registration),
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('registration_requests', [
            'id' => $registration->id,
            'status' => 'rejected',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'newuser@example.com']);
    }

    public function test_closed_tenant_user_cannot_login(): void
    {
        $owner = User::where('tenant_id', 1)->first();
        $tenant = Tenant::create([
            'slug' => 'closed_corp',
            'admin_email' => 'admin@closed.com',
        ]);
        $tenant->close($owner);
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
        $tenant = Tenant::create([
            'slug' => 'closed_corp',
            'admin_email' => 'admin@closed.com',
        ]);
        $tenant->close($owner);

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
        $this->assertDatabaseMissing('registration_requests', ['email' => 'newuser@example.com']);
    }

    public function test_register_sets_request_expiry(): void
    {
        Tenant::create(['slug' => 'acme_corp', 'admin_email' => 'admin@acme.com']);

        $this->post(route('auth.register.store'), [
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_slug' => 'acme_corp',
        ]);

        $this->assertDatabaseHas('registration_requests', [
            'email' => 'newuser@example.com',
            'status' => 'pending',
        ]);

        $this->assertNotNull(
            RegistrationRequest::where('email', 'newuser@example.com')->value('expires_at')
        );
    }

    public function test_pending_registration_login_returns_pending_message(): void
    {
        RegistrationRequest::create([
            'email' => 'pending@example.com',
            'password' => bcrypt('password123'),
            'status' => 'pending',
        ]);

        $response = $this->post(route('auth.login.store'), [
            'email' => 'pending@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('pending approval', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_rejected_registration_login_returns_declined_message(): void
    {
        RegistrationRequest::create([
            'email' => 'rejected@example.com',
            'password' => bcrypt('password123'),
            'status' => 'rejected',
        ]);

        $response = $this->post(route('auth.login.store'), [
            'email' => 'rejected@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('declined', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_expired_registration_login_returns_expired_message(): void
    {
        RegistrationRequest::create([
            'email' => 'expired@example.com',
            'password' => bcrypt('password123'),
            'status' => 'pending',
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->post(route('auth.login.store'), [
            'email' => 'expired@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('expired', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_login_with_unknown_email_still_says_invalid_credentials(): void
    {
        $response = $this->post(route('auth.login.store'), [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Invalid credentials', session('errors')->first('email'));
    }

    public function test_track_page_renders(): void
    {
        $this->get(route('auth.track'))->assertOk();
    }

    public function test_track_shows_pending_status_for_existing_email(): void
    {
        RegistrationRequest::create([
            'email' => 'pending@example.com',
            'password' => bcrypt('password123'),
            'status' => 'pending',
        ]);

        $response = $this->post(route('auth.track.store'), [
            'email' => 'pending@example.com',
        ]);

        $response->assertOk();
        $response->assertSee('Pending review');
        $response->assertSee('pending@example.com');
    }

    public function test_track_shows_accepted_status(): void
    {
        RegistrationRequest::create([
            'email' => 'approved@example.com',
            'password' => bcrypt('password123'),
            'status' => 'approved',
        ]);

        $response = $this->post(route('auth.track.store'), [
            'email' => 'approved@example.com',
        ]);

        $response->assertOk();
        $response->assertSee('Accepted');
    }

    public function test_track_reports_unknown_email_without_showing_status(): void
    {
        $response = $this->post(route('auth.track.store'), [
            'email' => 'nobody@example.com',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('No registration request', session('errors')->first('email'));
        $response->assertDontSee('Pending review');
    }
}
