<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\permaments\OwnerSeeder;
use Database\Seeders\permaments\SystemTenantSeeder;
use Database\Seeders\permaments\SystemTenantRoleSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // $this->call([
        //     TenantSeeder::class
        // ]);

        $this->call([
            SystemTenantRoleSeeder::class,
            SystemTenantSeeder::class,
            OwnerSeeder::class
        ]);
    }
}
