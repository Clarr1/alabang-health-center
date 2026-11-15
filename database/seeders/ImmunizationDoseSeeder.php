<?php

namespace Database\Seeders;

use App\Models\ImmunizationDose;
use Illuminate\Database\Seeder;

class ImmunizationDoseSeeder extends Seeder
{
    public function run(): void
    {
        ImmunizationDose::factory()->create();
    }
}
