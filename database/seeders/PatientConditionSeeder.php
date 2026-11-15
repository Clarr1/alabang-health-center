<?php

namespace Database\Seeders;

use App\Models\PatientCondition;
use Illuminate\Database\Seeder;

class PatientConditionSeeder extends Seeder
{
    public function run(): void
    {
        PatientCondition::factory()->create();
    }
}
