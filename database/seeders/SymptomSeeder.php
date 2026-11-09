<?php

namespace Database\Seeders;

use App\Models\Symptom;
use Illuminate\Database\Seeder;

class SymptomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['Fever', 'Cough', 'Sore Throat', 'Headache', 'Diarrhea', 'Vomiting', 'Rash', 'Body Pain'] as $name) {
            Symptom::firstOrCreate(['name' => $name]);
        }
    }
}
