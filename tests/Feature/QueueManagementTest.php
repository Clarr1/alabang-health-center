<?php

use App\Livewire\QueueDashboard;
use App\Livewire\QueueManagement;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('searches existing patients by name and ID and prevents duplicate adds', function () {
    $staff = User::factory()->create();
    $patient = PatientInformation::factory()->create(['first_name' => 'Maria', 'middle_name' => 'Reyes', 'last_name' => 'Santos']);
    $component = Livewire::actingAs($staff)->test(QueueManagement::class)
        ->set('patientSearch', 'Maria Santos')->assertSee('Maria')
        ->set('patientSearch', (string) $patient->patient_id)->assertSee('Maria')
        ->call('selectPatient', $patient->patient_id)
        ->set('queueNumber', '42')->call('prepareAssignment')->assertHasNoErrors()
        ->assertDispatched('queue-assignment-prepared', message: "Add Maria {$patient->middle_name} Santos to the queue with assigned number 42?")->assertSet('pendingQueueNumber', 42)
        ->call('addPatient')->assertHasNoErrors()
        ->assertSee('Assigned queue number: 42')
        ->assertSet('selectedPatientId', null)->assertSet('queueNumber', '')
        ->call('selectPatient', $patient->patient_id)->set('queueNumber', '99')
        ->call('prepareAssignment')->assertHasErrors('queue');
    expect(Queue::count())->toBe(1)->and(PatientInformation::count())->toBe(1);
});

it('filters by patient name ID queue number status and date and paginates', function () {
    $staff = User::factory()->create();
    $patient = PatientInformation::factory()->create(['first_name' => 'Maria', 'middle_name' => 'Reyes', 'last_name' => 'Santos']);
    Queue::factory()->create(['patient_id' => $patient->patient_id, 'queue_number' => 77, 'status' => 'Called']);
    foreach (range(1, 11) as $number) {
        Queue::factory()->create(['queue_number' => $number]);
    }
    $component = Livewire::actingAs($staff)->test(QueueManagement::class)
        ->assertSet('queueDate', now('Asia/Manila')->toDateString())
        ->set('search', 'Maria Santos')->assertSee('Maria')
        ->set('search', '77')->assertSee('Maria')
        ->set('search', (string) $patient->patient_id)->assertSee('Maria')
        ->set('search', '')->set('status', 'Called')->assertSee('Maria')
        ->set('status', 'Completed')->assertSee('No patients are queued')
        ->set('status', '')->call('setPage', 2)->assertSee('Maria')
        ->set('queueDate', '2000-01-01')->assertSee('No patients are queued');
});

it('shows staff actions and rejects manually submitted doctor actions', function () {
    $staff = User::factory()->create();
    $queue = Queue::factory()->create();
    Livewire::actingAs($staff)->test(QueueManagement::class)
        ->assertSee('Call Patient')->assertDontSee('Start Consultation')
        ->call('changeStatus', $queue->queue_id, 'Waiting', 'Called')->assertHasNoErrors()
        ->call('changeStatus', $queue->queue_id, 'Waiting', 'Called')->assertHasErrors('queue');
    Livewire::actingAs($staff)->test(QueueManagement::class)
        ->call('changeStatus', $queue->queue_id, 'Called', 'In Consultation')->assertForbidden();
    expect($queue->fresh()->status)->toBe('Called');
});

it('allows doctors to start consultation but not enqueue patients', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $queue = Queue::factory()->create(['status' => 'Ready for Consultation']);
    VitalSign::create(['queue_id' => $queue->queue_id, 'patient_id' => $queue->patient_id, 'recorded_by' => $doctor->user_id]);
    Livewire::actingAs($doctor)->test(QueueManagement::class)
        ->assertSee('Start Consultation')->assertDontSee('Add an existing patient')
        ->call('changeStatus', $queue->queue_id, 'Ready for Consultation', 'In Consultation')
        ->assertRedirect(route('queues.consultation', ['patient_id' => $queue->patient_id, 'queue_id' => $queue->queue_id]));
    Livewire::actingAs($doctor)->test(QueueManagement::class)
        ->call('addPatient')->assertForbidden();
});

it('updates dashboard statistics on subsequent Livewire requests', function () {
    $staff = User::factory()->create();
    Queue::factory()->create(['status' => 'Waiting']);
    $component = Livewire::actingAs($staff)->test(QueueDashboard::class);
    expect($component->viewData('statistics')['Waiting Patients'])->toBe(1);
    Queue::factory()->create(['status' => 'Completed']);
    $component->call('$refresh');
    expect($component->viewData('statistics')['Total Patients Queued Today'])->toBe(2)
        ->and($component->viewData('statistics')['Completed Patients'])->toBe(1);
});

it('handles invalid filters without querying an unintended date or status', function () {
    $component = Livewire::actingAs(User::factory()->create())->test(QueueManagement::class)
        ->set('queueDate', 'invalid')->assertHasErrors('queueDate')->assertSee('No patients are queued')
        ->set('queueDate', now('Asia/Manila')->toDateString())
        ->set('status', 'Invalid Status')->assertHasErrors('status')->assertSee('No patients are queued');
});

it('requires selection a valid number and a confirmation step before saving', function () {
    $patient = PatientInformation::factory()->create();
    $component = Livewire::actingAs(User::factory()->create())->test(QueueManagement::class)
        ->call('addPatient')->assertHasErrors('queue')
        ->call('prepareAssignment')->assertHasErrors(['selectedPatientId', 'queueNumber'])
        ->call('selectPatient', $patient->patient_id)
        ->call('prepareAssignment')->assertHasErrors('queueNumber')
        ->set('queueNumber', '1.5')->call('prepareAssignment')->assertHasErrors('queueNumber');
    expect(Queue::count())->toBe(0);
    $component->set('queueNumber', '42')->call('prepareAssignment')->assertHasNoErrors()
        ->assertSet('pendingPatientId', $patient->patient_id)->assertSet('pendingQueueNumber', 42)
        ->assertDispatched('queue-assignment-prepared', fn (string $event, array $parameters): bool => str_contains($parameters['message'], 'queue with assigned number 42?'));
    expect(Queue::count())->toBe(0);
    $component->set('queueNumber', '99')->call('addPatient')->assertHasNoErrors();
    expect(Queue::firstOrFail()->queue_number)->toBe(42);
});

it('reports a clear number conflict if another staff member saves after confirmation opens', function () {
    $firstStaff = User::factory()->create();
    $secondStaff = User::factory()->create();
    $firstPatient = PatientInformation::factory()->create();
    $secondPatient = PatientInformation::factory()->create();
    $first = Livewire::actingAs($firstStaff)->test(QueueManagement::class)
        ->call('selectPatient', $firstPatient->patient_id)->set('queueNumber', '42')
        ->call('prepareAssignment')->assertHasNoErrors();
    $second = Livewire::actingAs($secondStaff)->test(QueueManagement::class)
        ->call('selectPatient', $secondPatient->patient_id)->set('queueNumber', '42')
        ->call('prepareAssignment')->assertHasNoErrors();
    $this->actingAs($firstStaff);
    $first->call('addPatient')->assertHasNoErrors();
    $this->actingAs($secondStaff);
    $second->call('addPatient')->assertHasErrors('queueNumber')
        ->assertSee('already been assigned today')
        ->assertSet('selectedPatientId', $secondPatient->patient_id)->assertSet('queueNumber', '42')
        ->assertDispatched('queue-assignment-finished');
    expect(Queue::count())->toBe(1)->and(Queue::firstOrFail()->assigned_by)->toBe($firstStaff->user_id);
});

it('shows today’s used numbers including closed visits and sorts waiting patients numerically', function () {
    $staff = User::factory()->create();
    foreach ([10, 2, 101] as $number) {
        Queue::factory()->create(['queue_number' => $number, 'status' => 'Waiting']);
    }
    foreach (['Completed' => 42, 'Cancelled' => 50, 'No Show' => 60] as $status => $number) {
        Queue::factory()->create(['queue_number' => $number, 'status' => $status]);
    }
    $previousDate = now('Asia/Manila')->subDay()->toDateString();
    DB::table('queue_days')->insert(['queue_date' => $previousDate, 'last_number' => 0]);
    Queue::factory()->create(['queue_date' => $previousDate, 'queue_number' => 1]);
    $component = Livewire::actingAs($staff)->test(QueueManagement::class)->set('status', 'Waiting');
    expect($component->get('queues')->pluck('queue_number')->all())->toBe([2, 10, 101])
        ->and($component->get('assignedNumbers')->pluck('queue_number')->all())->toBe([2, 10, 42, 50, 60, 101]);
    Queue::factory()->create(['queue_number' => 99]);
    $component->call('$refresh');
    expect($component->get('assignedNumbers')->pluck('queue_number')->all())->toBe([2, 10, 42, 50, 60, 99, 101]);
});

it('requires confirmation again when the Manila date changes', function () {
    $this->travelTo(new DateTimeImmutable('2026-10-10 15:59:00 UTC'));
    $patient = PatientInformation::factory()->create();
    $component = Livewire::actingAs(User::factory()->create())->test(QueueManagement::class)
        ->call('selectPatient', $patient->patient_id)->set('queueNumber', '42')
        ->call('prepareAssignment')->assertHasNoErrors();
    $this->travelTo(new DateTimeImmutable('2026-10-10 16:01:00 UTC'));
    $component->call('addPatient')->assertHasErrors('queueNumber')->assertSee('queue date has changed');
    expect(Queue::count())->toBe(0);
    $component->call('prepareAssignment')->call('addPatient')->assertHasNoErrors();
    expect(Queue::firstOrFail()->queue_date->toDateString())->toBe('2026-10-11');
});

it('preselects the registered patient without assigning a number', function () {
    $patient = PatientInformation::factory()->create();
    Livewire::actingAs(User::factory()->create())->withQueryParams(['patient' => $patient->patient_id])
        ->test(QueueManagement::class)->assertSet('selectedPatientId', $patient->patient_id)
        ->assertSet('queueNumber', '')->assertSet('pendingQueueNumber', null);
    expect(Queue::count())->toBe(0);
});

it('invalidates a pending assignment when staff select another patient', function () {
    $patient = PatientInformation::factory()->create();
    $otherPatient = PatientInformation::factory()->create();
    Livewire::actingAs(User::factory()->create())->test(QueueManagement::class)
        ->call('selectPatient', $patient->patient_id)->set('queueNumber', '42')
        ->call('prepareAssignment')->assertHasNoErrors()
        ->call('selectPatient', $otherPatient->patient_id)
        ->call('addPatient')->assertHasErrors('queue');
    expect(Queue::count())->toBe(0);
});

it('requires authentication and denies queue clinical routes to the wrong role', function () {
    $queue = Queue::factory()->create(['status' => 'Vital Signs']);
    $this->get(route('queues.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role' => 'Doctor']))
        ->get(route('queues.vital-signs', ['queue_id' => $queue->queue_id, 'patient_id' => $queue->patient_id]))->assertForbidden();
    $this->actingAs(User::factory()->create())
        ->get(route('queues.consultation', ['queue_id' => $queue->queue_id, 'patient_id' => $queue->patient_id]))->assertForbidden();
});
