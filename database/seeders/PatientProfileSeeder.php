<?php

namespace Database\Seeders;

use App\Models\PatientProfile;
use Illuminate\Database\Seeder;

class PatientProfileSeeder extends Seeder
{
    public function run(): void
    {
        PatientProfile::factory()->create();
    }
}
