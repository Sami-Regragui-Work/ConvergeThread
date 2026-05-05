<?php

namespace Database\Seeders\permaments;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OwnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'sami.regragui.work@protonmail.com',
            ],
            [
                'username' => 'sami_regragui',
                'display_name' => 'Owner',
                'password' => Hash::make('@Srw181202#ConvergeThread'),
                'tenant_id' => 0,
                'tenant_role_id' => null,
                'banned_by_id' => null,
            ]
        );
    }
}
