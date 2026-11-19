<?php

use App\Livewire\EditPatient;
use App\Livewire\FollowUps;
use App\Livewire\PatientInformationForm;
use App\Livewire\VitalSignsForm;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\User;
use App\Models\VitalSign;
use App\Services\QueueWorkflow;
use App\Services\TextBee;
use Database\Factories\MedicalRecordFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(new DateTimeImmutable('2026-11-08 09:00:00 Asia/Manila'));
    Http::preventStrayRequests();
    $this->actingAs(User::factory()->create());
});

it('rejects invalid patient chronology and forged calculated ages without losing input', function (string $field, mixed $value, string $error) {
    $form = Livewire::test(PatientInformationForm::class)
        ->set('first_name', 'Synthetic')->set('last_name', 'Patient')
        ->set('birthdate', '2000-01-01')->set('address', 'Synthetic address');
    if ($field === 'age') {
        expect(fn () => $form->set($field, $value))->toThrow(CannotUpdateLockedPropertyException::class);
        expect(PatientInformation::count())->toBe(0);

        return;
    }
    $form->set($field, $value)->call('savePatient')->assertHasErrors($error)
        ->assertSet('first_name', 'Synthetic')->assertSet($field, $value)
        ->assertNotDispatched('patient-information-saved');
    expect(PatientInformation::count())->toBe(0);
})->with([
    'future birthdate' => ['birthdate', '2026-11-09', 'birthdate'],
    'forged age' => ['age', 12, 'age'],
    'registration before birth' => ['registration_date', '1999-01-01', 'registration_date'],
    'future registration' => ['registration_date', '2026-11-09', 'registration_date'],
]);

it('applies patient chronology validation to edits as well as registration', function () {
    $patient = PatientInformation::factory()->create();
    Livewire::test(EditPatient::class, ['patient' => $patient])
        ->set('birthdate', '2026-11-09')
        ->call('saveChanges')->assertHasErrors('birthdate')->assertNotDispatched('patient-updated');
    expect($patient->fresh()->birthdate)->toBe('1990-01-01');
});

it('validates a malformed birthdate payload without crashing the age calculation', function () {
    Livewire::test(PatientInformationForm::class)->set('birthdate', ['invalid'])
        ->call('savePatient')->assertHasErrors('birthdate');
    expect(PatientInformation::count())->toBe(0);
});

it('denies inactive staff and doctors at the workflow authorization boundary', function (string $role, string $ability) {
    $user = User::factory()->create(['role' => $role, 'is_active' => false]);
    $this->actingAs($user);
    expect(Gate::forUser($user)->denies($ability))->toBeTrue();
    $workflow = app(QueueWorkflow::class);
    if ($role === 'Doctor') {
        expect(fn () => $workflow->resumeConsultation(999999))->toThrow(AuthorizationException::class);
    } else {
        $patient = PatientInformation::factory()->create();
        expect(fn () => $workflow->addPatient($patient->patient_id))->toThrow(AuthorizationException::class);
    }
    expect(Queue::count())->toBe(0);
})->with([['Local Health Unit Staff', 'queue-register'], ['Doctor', 'queue-consult']]);

it('preserves cleared optional vital signs as null rather than zero', function () {
    $patient = PatientInformation::factory()->create();
    Livewire::test(VitalSignsForm::class, ['patient_id' => $patient->patient_id])
        ->set('temperature', null)->set('pulse_rate', null)->set('respiratory_rate', null)
        ->set('height', null)->set('weight', null)->call('saveVitalSigns')->assertHasNoErrors();
    $vital = VitalSign::sole();
    expect($vital->pulse_rate)->toBeNull()->and($vital->respiratory_rate)->toBeNull()
        ->and($vital->temperature)->toBeNull()->and($vital->height)->toBeNull()
        ->and($vital->weight)->toBeNull()->and($vital->bmi)->toBeNull();
});

it('leaves BMI empty when weight is cleared and preserves an explicitly recorded zero', function () {
    $patient = PatientInformation::factory()->create();
    Livewire::test(VitalSignsForm::class, ['patient_id' => $patient->patient_id])
        ->set('height', 160)->set('weight', 55)->assertSet('bmi', 21.5)
        ->set('weight', null)->assertSet('bmi', '')
        ->set('pulse_rate', 0)->call('saveVitalSigns')->assertHasNoErrors();
    expect(VitalSign::sole()->pulse_rate)->toBe(0)->and(VitalSign::sole()->bmi)->toBeNull();
});

it('rejects incomplete and malformed blood pressure without advancing the visit', function (string $pressure) {
    $queue = Queue::factory()->create(['status' => 'Vital Signs']);
    Livewire::test(VitalSignsForm::class, ['patient_id' => $queue->patient_id, 'queue_id' => $queue->queue_id])
        ->set('blood_pressure', $pressure)->call('saveVitalSigns')
        ->assertHasErrors('blood_pressure')->assertSet('blood_pressure', $pressure);
    expect(VitalSign::count())->toBe(0)->and($queue->fresh()->status)->toBe('Vital Signs');
})->with(['120/', '/80', 'invalid', '-120/80', '120/80/90']);

it('does not advance a visit using another patients vital signs', function () {
    $queue = Queue::factory()->create(['status' => 'Vital Signs']);
    VitalSign::create(['queue_id' => $queue->queue_id, 'patient_id' => PatientInformation::factory()->create()->patient_id, 'recorded_by' => auth()->id()]);
    expect(fn () => app(QueueWorkflow::class)->transition($queue->queue_id, 'Vital Signs', 'Ready for Consultation'))
        ->toThrow(ValidationException::class);
    expect($queue->fresh()->status)->toBe('Vital Signs');
});

it('does not complete a visit using an unrelated consultation', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $this->actingAs($doctor);
    $queue = Queue::factory()->create(['status' => 'In Consultation', 'consulting_doctor_id' => $doctor->user_id]);
    MedicalRecordFactory::new()->create(['queue_id' => $queue->queue_id]);
    expect(fn () => app(QueueWorkflow::class)->transition($queue->queue_id, 'In Consultation', 'Completed'))
        ->toThrow(ValidationException::class);
    expect($queue->fresh()->status)->toBe('In Consultation');
});

it('reports invalid follow up filters and clears their errors', function (string $field, string $value) {
    Livewire::actingAs(User::factory()->create(['role' => 'Doctor']))->test(FollowUps::class)->set($field, $value)->assertHasErrors($field)
        ->assertSeeHtml('text-red-600')
        ->call('clearFilters')->assertHasNoErrors()->assertSet($field, '');
})->with([['from', '2026-02-30'], ['to', 'not-a-date'], ['status', 'Unsupported']]);

it('rejects a reversed follow up date range', function () {
    Livewire::actingAs(User::factory()->create(['role' => 'Doctor']))->test(FollowUps::class)->set('from', '2026-11-10')->set('to', '2026-11-09')
        ->assertHasErrors('to')->call('clearFilters')->assertHasNoErrors();
});

it('tolerates malformed delivery reports without claiming delivery', function (mixed $messages) {
    config(['textbee.api_key' => 'test-placeholder', 'textbee.base_url' => 'https://sms.example.test']);
    Http::fake(['*' => Http::response(['data' => ['messages' => $messages]])]);
    expect(app(TextBee::class)->delivery('test-device', 'test-batch', '+639171234567'))->toBeNull();
})->with(['null messages' => [null], 'string messages' => ['unavailable'], 'invalid entry' => [[null, 'invalid']]]);
