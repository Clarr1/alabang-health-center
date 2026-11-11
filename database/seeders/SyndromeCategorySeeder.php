<?php

namespace Database\Seeders;

use App\Models\SyndromeCategory;
use Illuminate\Database\Seeder;

class SyndromeCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Febrile Syndrome', 'Respiratory Syndrome', 'Gastrointestinal Syndrome', 'Rash-Associated Syndrome'] as $name) {
            SyndromeCategory::firstOrCreate(['name' => $name], ['description' => 'Unreviewed category structure. No clinical definition or symptom mappings approved.', 'is_active' => false]);
        }
    }
}
