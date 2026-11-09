<?php

use App\Livewire\DiagnosisManagement;
use App\Models\Diagnosis;
use App\Models\User;
use Database\Factories\MedicalRecordFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('allows doctors and admins to add view search edit and change diagnosis availability', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->get(route('diagnoses.index'))->assertOk()->assertSee('Diagnosis Management');
    $component = Livewire::actingAs($user)->test(DiagnosisManagement::class)
        ->call('createDiagnosis')->set('name', 'Seasonal allergies')->set('code', 'J30')->set('description', 'Allergic symptoms')
        ->call('saveDiagnosis')->assertHasNoErrors()->assertSet('showForm', false)->assertSee('Seasonal allergies');
    $diagnosis = Diagnosis::sole();
    $record = MedicalRecordFactory::new()->create(['diagnosis_id' => $diagnosis->id, 'diagnosis' => 'Seasonal allergies']);
    $component->set('search', 'not found')->assertDontSee('Seasonal allergies')->set('search', 'allerg')->assertSee('Seasonal allergies')
        ->call('editDiagnosis', $diagnosis->id)->assertSet('code', 'J30')->set('name', 'Allergic rhinitis')
        ->call('saveDiagnosis')->assertHasNoErrors()->set('search', '')
        ->call('setActive', $diagnosis->id, false)->assertHasNoErrors();
    expect($diagnosis->fresh()->is_active)->toBeFalse()->and($record->fresh()->diagnosis)->toBe('Seasonal allergies');
    $component->call('setActive', $diagnosis->id, true)->assertHasNoErrors();
    expect($diagnosis->fresh()->is_active)->toBeTrue();
    expect(fn () => $diagnosis->delete())->toThrow(QueryException::class);
})->with(['Doctor', 'Admin']);

it('requires a name and rejects normalized duplicate names on create and edit', function () {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $existing = Diagnosis::factory()->create(['name' => 'Seasonal allergies']);
    $other = Diagnosis::factory()->create(['name' => 'Other diagnosis']);
    $component = Livewire::actingAs($doctor)->test(DiagnosisManagement::class)->call('createDiagnosis')
        ->call('saveDiagnosis')->assertHasErrors('name')->set('name', ' SEASONAL   ALLERGIES ')
        ->call('saveDiagnosis')->assertHasErrors('name')->assertSet('showForm', true)
        ->call('editDiagnosis', $other->id)->set('name', 'seasonal allergies')->call('saveDiagnosis')->assertHasErrors('name')
        ->call('editDiagnosis', $existing->id)->call('saveDiagnosis')->assertHasNoErrors();
    expect(Diagnosis::count())->toBe(2)->and($other->fresh()->name)->toBe('Other diagnosis');
});

it('reviews proposed diagnoses without modifying medical record wording', function (string $decision) {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $proposal = Diagnosis::factory()->create(['name' => 'Possible viral illness', 'review_status' => 'pending', 'is_active' => false]);
    $record = MedicalRecordFactory::new()->create(['diagnosis_id' => $proposal->id, 'diagnosis' => 'Possible viral illness, pending review']);
    Livewire::actingAs($doctor)->test(DiagnosisManagement::class)->set('statusFilter', 'pending')->assertSee('Possible viral illness')
        ->call('reviewDiagnosis', $proposal->id, $decision)->assertHasNoErrors();
    expect($proposal->fresh()->review_status)->toBe($decision)->and($proposal->fresh()->is_active)->toBe($decision === 'approved')
        ->and($record->fresh()->diagnosis)->toBe('Possible viral illness, pending review');
})->with(['approved', 'rejected']);

it('denies catalog access to staff unsupported roles and inactive users', function (string $role, bool $active) {
    DB::statement('PRAGMA ignore_check_constraints = ON');
    try {
        $user = User::factory()->create(['role' => $role, 'is_active' => $active]);
    } finally {
        DB::statement('PRAGMA ignore_check_constraints = OFF');
    }
    $this->actingAs($user)->get(route('diagnoses.index'))->assertForbidden();
    Livewire::actingAs($user)->test(DiagnosisManagement::class)->assertForbidden();
})->with([
    ['Local Health Unit Staff', true], ['Unassigned', true], ['Doctor', false], ['Admin', false],
]);

it('reauthorizes every catalog mutation after access is revoked', function (string $action, array $arguments) {
    $doctor = User::factory()->create(['role' => 'Doctor']);
    Diagnosis::factory()->create(['id' => 1, 'review_status' => 'pending', 'is_active' => false]);
    $component = Livewire::actingAs($doctor)->test(DiagnosisManagement::class);
    $doctor->role = 'Local Health Unit Staff';
    $doctor->save();
    Livewire::actingAs($doctor);
    $component->call($action, ...$arguments)->assertForbidden();
    expect(Diagnosis::sole()->review_status)->toBe('pending')->and(Diagnosis::sole()->is_active)->toBeFalse();
})->with([
    ['createDiagnosis', []], ['editDiagnosis', [1]], ['saveDiagnosis', []], ['setActive', [1, true]], ['reviewDiagnosis', [1, 'approved']],
]);

it('requires authentication for the diagnosis directory', function () {
    $this->get(route('diagnoses.index'))->assertRedirect(route('login'));
});
