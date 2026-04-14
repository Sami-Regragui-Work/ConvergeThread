<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create([
            'name' => 'ConvergeThread Test Tenant',
            'admin_email' => 'admin@convergethread.test',
        ]);

        // Admin role
        TenantRole::create([
            'tenant_id' => $tenant->id,
            'name' => 'admin',
            'permissions' => ['*'],
        ]);

        // Test role
        TenantRole::create([
            'tenant_id' => $tenant->id,
            'name' => 'member',
            'permissions' => ['messages:read', 'messages:send'],
        ]);

        // Admin user
        User::create([
            'email' => 'admin@convergethread.test',
            'password' => bcrypt('pass'),
            'username' => 'admin',
            'display_name' => 'Admin',
            'tenant_id' => $tenant->id,
        ]);
    }
}
