<?php

namespace Database\Seeders\permaments;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

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
                'name' => 'ConvergeThread Owner',
                'admin_email' => 'sami.regragui.work@protonmail.com',
                'closed_by_id' => null,
            ]
        );
    }
}
