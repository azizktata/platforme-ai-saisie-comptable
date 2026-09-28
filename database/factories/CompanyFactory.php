<?php

namespace Database\Factories;

use App\Models\Cabinet;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cabinet_id' => Cabinet::factory(),
            'name' => fake()->unique()->company(),
            'legal_name' => fake()->company(),
            'tax_identifier' => fake()->optional()->bothify('########?'),
            'activity' => fake()->optional()->jobTitle(),
            'sector' => fake()->optional()->bs(),
            'country_code' => 'TN',
            'currency' => 'TND',
        ];
    }
}
