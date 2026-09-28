<?php

namespace Database\Factories;

use App\Models\Cabinet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'cabinet_id' => Cabinet::factory(),
            'cabinet_role' => User::CABINET_ROLE_MEMBER,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function cabinetAdmin(): static
    {
        return $this->state(fn (): array => [
            'cabinet_role' => User::CABINET_ROLE_ADMIN,
        ]);
    }
}
