<?php

namespace Database\Seeders;

use App\Models\PatientProfileAudit;
use Illuminate\Database\Seeder;

class PatientProfileAuditSeeder extends Seeder
{
    public function run(): void
    {
        PatientProfileAudit::factory()->create();
    }
}
