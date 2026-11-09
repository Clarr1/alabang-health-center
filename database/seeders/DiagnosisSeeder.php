<?php

namespace Database\Seeders;

use App\Models\Diagnosis;
use Illuminate\Database\Seeder;

class DiagnosisSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Seasonal allergies', 'Acute upper respiratory infection', 'Hypertension'] as $name) {
            Diagnosis::firstOrCreate(['normalized_name' => Diagnosis::normalizeName($name)], ['name' => $name]);
        }
    }
}
