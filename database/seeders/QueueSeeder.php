<?php

namespace Database\Seeders;

use App\Models\Queue;
use Illuminate\Database\Seeder;

class QueueSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('local')) {
            foreach (range(1, 5) as $visit) {
                Queue::factory()->create(['queue_number' => $visit]);
            }
        }
    }
}
