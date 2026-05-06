<?php

namespace Database\Seeders\permaments;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SystemTenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Tenant::updateOrCreate(
            [
                'id' => 1,
            ],
            [
                'slug' => Str::slug('ConvergeThread Owner', '_'),
                'admin_email' => 'sami.regragui.work@protonmail.com',
                'closed_by_id' => null,
            ]
        );
    }
}
