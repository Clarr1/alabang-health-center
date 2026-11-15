<?php

namespace Database\Factories;

use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Household> */
class HouseholdFactory extends Factory
{
    public function definition(): array
    {
        return ['family_number' => 'FN-'.fake()->unique()->numerify('########'), 'label' => fake()->lastName().' Family', 'address' => fake()->address(), 'family_count' => 1];
    }
}
