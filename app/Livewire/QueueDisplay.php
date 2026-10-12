<?php

namespace App\Livewire;

use App\Models\Queue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.queue-display')]
class QueueDisplay extends Component
{
    public function render(): View
    {
        Gate::authorize('queue-view');
        $today = now('Asia/Manila');
        $queues = Queue::query()
            ->whereDate('queue_date', $today->toDateString())
            ->whereNotIn('status', Queue::TERMINAL_STATUSES)
            ->orderBy('queue_number')
            ->get(['queue_number', 'status', 'called_at', 'updated_at']);

        $stages = [
            'vital-signs' => [
                'name' => 'Vital Signs',
                'serving' => $queues->whereIn('status', ['Called', 'Vital Signs'])->map(fn (Queue $queue): array => [
                    'number' => (int) $queue->queue_number,
                    'highlighted' => $queue->called_at !== null && $queue->called_at->gte($today->copy()->subSeconds(15)),
                ])->values()->all(),
                'waiting' => $queues->where('status', 'Waiting')->pluck('queue_number')->map(fn (mixed $number): int => (int) $number)->values()->all(),
            ],
            'consultation' => [
                'name' => 'Doctor Consultation',
                'serving' => $queues->where('status', 'In Consultation')->map(fn (Queue $queue): array => [
                    'number' => (int) $queue->queue_number,
                    'highlighted' => $queue->updated_at->gte($today->copy()->subSeconds(15)),
                ])->values()->all(),
                'waiting' => $queues->where('status', 'Ready for Consultation')->pluck('queue_number')->map(fn (mixed $number): int => (int) $number)->values()->all(),
            ],
        ];

        return view('livewire.queue-display', [
            'stages' => $stages,
            'displayDate' => $today->format('l, F j, Y'),
            'displayDay' => $today->toDateString(),
        ]);
    }
}
