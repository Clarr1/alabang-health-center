<?php

namespace Database\Factories;

use App\Models\SyndromeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SyndromeCategory> */
class SyndromeCategoryFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'SIMULATED '.fake()->unique()->words(3, true), 'description' => 'Simulated test definition; not a clinical standard.', 'is_active' => true];
    }
}
