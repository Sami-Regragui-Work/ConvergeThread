<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'username' => fake()->unique()->userName(),
            'display_name' => fake()->name(),
            'password' => static::$password ??= Hash::make('password'),
            'tenant_id' => 1,
            'tenant_role_id' => null,
            'banned_by_id' => null,
        ];
    }
}
