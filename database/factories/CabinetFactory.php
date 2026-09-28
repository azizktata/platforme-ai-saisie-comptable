<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<\App\Models\Cabinet> */
class CabinetFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company().' Cabinet';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
        ];
    }
}
