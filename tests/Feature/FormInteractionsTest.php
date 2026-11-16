<?php

use App\Livewire\DetectionSettings;
use App\Livewire\MedicalRecordsForm;
use App\Livewire\PatientProfileForm;
use App\Livewire\SyndromeManagement;
use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\Symptom;
use App\Models\User;
use App\Services\IndividualTreatmentRecord;
use Database\Factories\VitalSignFactory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Livewire\Drawer\Utils;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** @return array{snapshot: string, effects: array<string, mixed>} */
function interactionResponse(Testable $form): array
{
    return ['snapshot' => json_encode($form->snapshot, JSON_THROW_ON_ERROR), 'effects' => $form->effects];
}

function interactionHtml(Testable $form): string
{
    $html = preg_replace('/ wire:(snapshot|effects)="[^"]*"/', '', $form->html());
    $html = Utils::insertAttributesIntoHtmlRoot($html, ['wire:snapshot' => json_encode($form->snapshot, JSON_THROW_ON_ERROR), 'wire:effects' => '{}']);

    return view('layouts.app', ['slot' => new HtmlString($html)])->render();
}

/** @param array<string, mixed> $fixture */
function saveInteractionFixture(string $name, array $fixture): void
{
    if (filter_var(getenv('AHC_FORM_BROWSER_FIXTURE'), FILTER_VALIDATE_BOOLEAN)) {
        File::ensureDirectoryExists(storage_path('app/private'));
        File::put(storage_path('app/private/form-interaction-'.$name.'.json'), json_encode($fixture, JSON_THROW_ON_ERROR));
    }
}

it('keeps independent examination arrays through validation drafts resume and completion', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $queue = Queue::factory()->create(['status' => 'Ready for Consultation']);
    VitalSignFactory::new()->create(['queue_id' => $queue->queue_id, 'patient_id' => $queue->patient_id, 'recorded_by' => $doctor->user_id]);
    $form = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)->call('startConsultation', $queue->queue_id);
    expect($form->get('physical_examination'))->toBe(IndividualTreatmentRecord::emptyExamination());
    $fixture = ['html' => interactionHtml($form), 'responses' => []];
    $findings = ['skin' => ['Rashes'], 'heent' => ['Nasal Discharge'], 'chest_lungs' => ['Wheezes'], 'heart' => ['Murmurs'], 'abdomen' => ['Tenderness'], 'extremities' => ['Normal Gait']];
    foreach ($findings as $group => $values) {
        $form->set('physical_examination.'.$group.'.findings', $values);
    }
    $form->set('chief_complaint', 'Interaction test complaint')
        ->set('clinicalNotes.subjective_notes', 'Retained narrative')
        ->set('clinicalNotes.other_pe_findings', 'Additional examination notes')
        ->set('symptomSearch', 'F');
    $fixture['responses']['searchF'] = interactionResponse($form);
    $fever = Symptom::where('name', 'Fever')->value('id');
    $cough = Symptom::where('name', 'Cough')->value('id');
    $form->call('selectSymptom', $fever)->assertSet('symptomSearch', 'F');
    $fixture['responses']['selectF'] = interactionResponse($form);
    $form->set('symptomSearch', 'C');
    $fixture['responses']['searchC'] = interactionResponse($form);
    $form->call('selectSymptom', $cough)->assertSet('symptomSearch', 'C');
    $fixture['responses']['selectC'] = interactionResponse($form);
    $form->call('removeSymptom', $fever)->assertSet('selectedSymptomIds', [$cough]);
    $fixture['responses']['removeF'] = interactionResponse($form);
    $form->set('diagnosisSearch', 'not catalog');
    $fixture['responses']['diagnosisSearch'] = interactionResponse($form);
    $form->call('selectDiagnosis', 'other');
    $fixture['responses']['diagnosisOther'] = interactionResponse($form);
    $form->call('saveMedicalRecord')->assertHasErrors(['diagnosis', 'treatment']);
    $fixture['responses']['invalid'] = interactionResponse($form);
    $form->assertSet('clinicalNotes.subjective_notes', 'Retained narrative')->call('saveDraft')->assertHasNoErrors();
    $fixture['responses']['draft'] = interactionResponse($form);
    saveInteractionFixture('consultation', $fixture);
    $resumed = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)->call('resumeConsultation', $queue->queue_id);
    foreach ($findings as $group => $values) {
        $resumed->assertSet('physical_examination.'.$group.'.findings', $values);
    }
    saveInteractionFixture('resumed', ['html' => interactionHtml($resumed), 'responses' => []]);
    $resumed->set('diagnosis', 'Clinician entered wording')->set('treatment', 'Care plan')->call('saveMedicalRecord')->assertHasNoErrors();
    $record = MedicalRecord::sole();
    foreach ($findings as $group => $values) {
        expect($record->physical_examination[$group]['findings'])->toBe($values);
    }
    expect($record->physical_examination['skin']['findings'])->not->toContain('Pallor', 'Jaundice', 'Good Skin Turgor');
});

it('initializes missing examination groups when resuming older sparse drafts', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $queue = Queue::factory()->create(['status' => 'In Consultation', 'consulting_doctor_id' => $doctor->user_id]);
    VitalSignFactory::new()->create(['queue_id' => $queue->queue_id, 'patient_id' => $queue->patient_id]);
    $queue->forceFill(['consultation_draft' => ['physical_examination' => ['skin' => ['findings' => ['Pallor']]]]])->save();
    $form = Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)->call('resumeConsultation', $queue->queue_id)
        ->assertSet('physical_examination.skin.findings', ['Pallor']);
    foreach (array_diff(array_keys(IndividualTreatmentRecord::EXAMINATIONS), ['skin']) as $group) {
        $form->assertSet('physical_examination.'.$group.'.findings', []);
    }
});

it('retains profile selections and dose row identities while conditional fields change', function () {
    $patient = PatientInformation::factory()->create();
    $form = Livewire::actingAs(User::factory()->create(['role' => 'Admin']))->test(PatientProfileForm::class, ['patientId' => $patient->patient_id])->call('editProfile');
    $fixture = ['html' => interactionHtml($form), 'responses' => []];
    $form->set('profile.civil_status', 'Married');
    $fixture['responses']['married'] = interactionResponse($form);
    $form->set('profile.four_ps', 'Yes');
    $fixture['responses']['fourPs'] = interactionResponse($form);
    $form->set('selectedConditions', ['Asthma']);
    $fixture['responses']['asthma'] = interactionResponse($form);
    $form->call('addDose');
    $fixture['responses']['addFirst'] = interactionResponse($form);
    $firstKey = $form->get('newDoseKeys.0');
    $form->call('addDose');
    $fixture['responses']['addSecond'] = interactionResponse($form);
    $secondKey = $form->get('newDoseKeys.1');
    $form->set('newDoses.0.vaccine_name', 'IPV');
    $fixture['responses']['firstVaccine'] = interactionResponse($form);
    $form->set('newDoses.1.vaccine_name', 'OPV');
    $fixture['responses']['secondVaccine'] = interactionResponse($form);
    $form->call('removeDose', 0)->assertSet('newDoses.0.vaccine_name', 'OPV')
        ->assertSet('newDoseKeys', [$secondKey])->assertSet('profile.civil_status', 'Married')->assertSet('selectedConditions', ['Asthma']);
    expect($firstKey)->not->toBe($secondKey);
    $fixture['responses']['removeFirst'] = interactionResponse($form);
    saveInteractionFixture('profile', $fixture);
    $form->call('addDose')->assertSet('newDoses.0.vaccine_name', 'OPV');
    expect($form->get('newDoseKeys.1'))->not->toBe($firstKey)->not->toBe($secondKey);
});

it('renders separate syndrome checkbox arrays and single selection sensitivity controls', function () {
    Livewire::actingAs(User::factory()->create(['role' => 'Admin']));
    $syndromes = Livewire::test(SyndromeManagement::class)->assertSet('symptomIds', []);
    saveInteractionFixture('syndromes', ['html' => interactionHtml($syndromes), 'responses' => []]);
    $settings = Livewire::test(DetectionSettings::class);
    saveInteractionFixture('settings', ['html' => interactionHtml($settings), 'responses' => []]);
});

it('allows interactive dropdown content inside a modal without closing either parent', function () {
    $this->actingAs(User::factory()->create(['role' => 'Admin']));
    $content = Blade::render(<<<'BLADE'
<div x-data="{ picked: [], term: '' }">
    <x-modal name="interaction-modal" :show="true">
        <h2>Interactive modal</h2>
        <x-dropdown :close-on-select="false">
            <x-slot name="trigger"><button type="button" id="multi-trigger">Choose several</button></x-slot>
            <x-slot name="content">
                <input id="modal-search" x-model="term" aria-label="Search modal options">
                <label><input id="modal-first" type="checkbox" x-model="picked" value="first">First</label>
                <label><input id="modal-second" type="checkbox" x-model="picked" value="second">Second</label>
            </x-slot>
        </x-dropdown>
        <button id="modal-outside" type="button">Elsewhere in modal</button>
    </x-modal>
</div>
BLADE);
    $html = view('layouts.app', ['slot' => new HtmlString($content)])->render();
    expect($content)->toContain('Interactive modal', 'Choose several', 'Search modal options');
    saveInteractionFixture('modal', ['html' => $html, 'responses' => []]);
});
