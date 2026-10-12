<?php

namespace App\Livewire;

use App\Models\Queue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class QueueDashboard extends Component
{
    public function render(): View
    {
        Gate::authorize('queue-view');
        $counts = Queue::whereDate('queue_date', now('Asia/Manila')->toDateString())
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $statistics = [
            'Total Patients Queued Today' => $counts->sum(),
            'Waiting Patients' => $counts->get('Waiting', 0),
            'Patients Undergoing Vital Signs' => $counts->get('Vital Signs', 0),
            'Patients Ready for Consultation' => $counts->get('Ready for Consultation', 0),
            'Patients In Consultation' => $counts->get('In Consultation', 0),
            'Completed Patients' => $counts->get('Completed', 0),
        ];

        return view('livewire.queue-dashboard', compact('statistics'));
    }
}
