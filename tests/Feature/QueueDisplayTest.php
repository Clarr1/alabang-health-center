<?php

use App\Livewire\QueueDisplay;
use App\Livewire\QueueManagement;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\User;
use App\Services\QueueWorkflow;
use Livewire\Livewire;

it('groups all serving and upcoming numbers by service area in numeric order without patient information', function () {
    $staff = User::factory()->create();
    $patient = PatientInformation::factory()->create(['first_name' => 'PrivatePatientName', 'last_name' => 'PrivateSurname']);
    foreach ([
        [101, 'Waiting'], [2, 'Waiting'], [10, 'Waiting'],
        [21, 'Called'], [22, 'Vital Signs'], [23, 'Vital Signs'],
        [31, 'Ready for Consultation'], [3, 'Ready for Consultation'],
        [41, 'In Consultation'], [42, 'In Consultation'],
        [51, 'Completed'], [52, 'Cancelled'], [53, 'No Show'],
    ] as [$number, $status]) {
        Queue::factory()->create([
            'queue_number' => $number, 'status' => $status,
            'patient_id' => $number === 21 || in_array($status, Queue::TERMINAL_STATUSES, true) ? $patient->patient_id : PatientInformation::factory(),
        ]);
    }

    Livewire::actingAs($staff)->test(QueueDisplay::class)
        ->assertViewHas('stages', fn (array $stages): bool => array_column($stages['vital-signs']['serving'], 'number') === [21, 22, 23]
            && $stages['vital-signs']['waiting'] === [2, 10, 101]
            && array_column($stages['consultation']['serving'], 'number') === [41, 42]
            && $stages['consultation']['waiting'] === [3, 31])
        ->assertSee('wire:poll.2s.keep-alive', false)
        ->assertDontSee('PrivatePatientName')->assertDontSee('PrivateSurname')
        ->assertDontSee('patient_id')->assertDontSee('patient_information')
        ->assertDontSee('Call Patient')->assertDontSee('Cancel');

    $this->actingAs($staff)->get(route('queues.display'))->assertOk()
        ->assertSee('Fullscreen')->assertDontSee($staff->name)
        ->assertDontSee('PrivatePatientName');
});

it('refreshes an independent display when staff change queues and removes terminal visits', function () {
    $staff = User::factory()->create();
    $queue = Queue::factory()->create(['queue_number' => 27]);
    $display = Livewire::actingAs($staff)->test(QueueDisplay::class);
    $manager = Livewire::actingAs($staff)->test(QueueManagement::class);
    $manager->call('changeStatus', $queue->queue_id, 'Waiting', 'Called')->assertHasNoErrors();
    $display->call('$refresh')->assertViewHas('stages', fn (array $stages): bool => array_column($stages['vital-signs']['serving'], 'number') === [27]
        && $stages['vital-signs']['waiting'] === []);
    $manager->call('changeStatus', $queue->queue_id, 'Called', 'Vital Signs')->assertHasNoErrors();
    $display->call('$refresh')->assertViewHas('stages', fn (array $stages): bool => array_column($stages['vital-signs']['serving'], 'number') === [27]);

    foreach (['Completed', 'Cancelled', 'No Show'] as $status) {
        $queue->update(['status' => $status]);
        $display->call('$refresh')->assertSee('Nobody is being served here right now.')->assertSee('No waiting numbers.')
            ->assertViewHas('stages', fn (array $stages): bool => $stages['vital-signs']['serving'] === []);
    }

    $doctor = User::factory()->create(['role' => 'Doctor']);
    $consultation = Queue::factory()->create(['queue_number' => 90, 'status' => 'Ready for Consultation']);
    $this->actingAs($doctor);
    app(QueueWorkflow::class)->transition($consultation->queue_id, 'Ready for Consultation', 'In Consultation');
    $display->call('$refresh')->assertViewHas('stages', fn (array $stages): bool => array_column($stages['consultation']['serving'], 'number') === [90]
        && $stages['consultation']['waiting'] === []);
});

it('uses the Manila day and refreshes correctly across midnight', function () {
    $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 10, 10)->setTime(15, 59, 59));
    $staff = User::factory()->create();
    Queue::factory()->create(['queue_number' => 88, 'status' => 'Called']);
    $display = Livewire::actingAs($staff)->test(QueueDisplay::class)
        ->assertViewHas('displayDay', '2026-10-10');
    $this->travel(2)->seconds();
    Queue::factory()->create(['queue_number' => 9, 'status' => 'Called']);
    $display->call('$refresh')->assertViewHas('displayDay', '2026-10-11')
        ->assertViewHas('stages', fn (array $stages): bool => array_column($stages['vital-signs']['serving'], 'number') === [9]);
});

it('highlights recent calls and consultation starts temporarily without hiding other serving numbers', function () {
    $staff = User::factory()->create();
    Queue::factory()->create(['queue_number' => 7, 'status' => 'Called', 'called_at' => now()]);
    Queue::factory()->create(['queue_number' => 8, 'status' => 'Vital Signs', 'called_at' => now()->subMinute()]);
    Queue::factory()->create(['queue_number' => 9, 'status' => 'In Consultation']);
    $display = Livewire::actingAs($staff)->test(QueueDisplay::class)
        ->assertViewHas('stages', fn (array $stages): bool => array_column($stages['vital-signs']['serving'], 'highlighted') === [true, false]
            && $stages['consultation']['serving'][0]['highlighted'] === true)
        ->assertSee('Proceed now');
    $this->travel(16)->seconds();
    $display->call('$refresh')->assertDontSee('Proceed now')
        ->assertViewHas('stages', fn (array $stages): bool => array_column($stages['vital-signs']['serving'], 'number') === [7, 8]);
});

it('requires authorized login and provides a separate-tab display link', function () {
    $this->get(route('queues.display'))->assertRedirect(route('login'));
    $user = User::factory()->create();
    $user->role = 'Unassigned';
    $this->actingAs($user)->get(route('queues.display'))->assertForbidden();
    Livewire::actingAs($user)->test(QueueDisplay::class)->assertForbidden();

    foreach (['Admin', 'Local Health Unit Staff', 'Doctor'] as $role) {
        $staff = User::factory()->create(['role' => $role]);
        $this->actingAs($staff)->get(route('queues.display'))->assertOk();
        Livewire::actingAs($staff)->test(QueueManagement::class)
            ->assertSee('Open Queue Display')
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }
});
