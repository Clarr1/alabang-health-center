<?php

use App\Livewire\FollowUps;
use App\Livewire\MedicalRecordsForm;
use App\Livewire\MedicalRecordsTable;
use App\Livewire\PatientTable;
use App\Livewire\QueueManagement;
use App\Livewire\SyndromicSurveillance;
use App\Livewire\UsersRoles;
use App\Models\User;
use Database\Factories\MedicalRecordFactory;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

it('enforces the module matrix on URLs and independent Livewire mounts', function (string $role, bool $staff, bool $medical, bool $admin) {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user);
    foreach ([
        ['patients.index', PatientTable::class, $staff],
        ['queues.index', QueueManagement::class, $staff],
        ['medical-records.index', MedicalRecordsTable::class, $medical],
        ['doctors-consultation.index', MedicalRecordsForm::class, $medical],
        ['follow-ups.index', FollowUps::class, $medical],
        ['surveillance.index', SyndromicSurveillance::class, $medical],
        ['users-roles.index', UsersRoles::class, $admin],
    ] as [$route, $component, $allowed]) {
        $this->get(route($route))->assertStatus($allowed ? 200 : 403);
        Livewire::test($component)->assertStatus($allowed ? 200 : 403);
    }
    $this->get(route('patient.information-form'))->assertStatus($staff ? 200 : 403);
    $this->get(route('surveillance.settings'))->assertStatus($admin ? 200 : 403);
    $dashboard = $this->get(route('dashboard'))->assertOk();
    foreach (['Patient Record' => $staff, 'Queue Management' => $staff, 'Medical Records' => $medical, 'Follow-ups' => $medical, 'Syndromic Surveillance' => $medical, 'Users &amp; Roles' => $admin] as $label => $allowed) {
        if ($allowed) {
            $dashboard->assertSee('aria-label="'.$label.'"', false);
        } else {
            $dashboard->assertDontSee('aria-label="'.$label.'"', false);
        }
    }
})->with([
    ['Local Health Unit Staff', true, false, false],
    ['Doctor', false, true, false],
    ['Admin', true, true, true],
]);

it('blocks staff from medical details and exports even with valid patient identifiers', function () {
    $record = MedicalRecordFactory::new()->create();
    $patient = $record->patient;
    $this->actingAs(User::factory()->create());
    foreach (['/medical-records/records/'.$patient->patient_id, '/medical-records/records/'.$patient->patient_id.'/visits/'.$record->getKey().'/pdf', '/medical-records/records/'.$patient->patient_id.'/visits/'.$record->getKey().'/download', '/medical-records/records/'.$patient->patient_id.'/visits/'.$record->getKey().'/print'] as $url) {
        $this->get($url)->assertForbidden();
    }
    $form = Livewire::test(PatientTable::class)->call('viewPatient', $patient->patient_id)
        ->assertDontSee('Recent Vital Signs')->assertDontSee('Medical Records');
    expect($form->get('selectedPatient')->relationLoaded('medicalRecords'))->toBeFalse();
});

it('rechecks livewire operations after the authenticated role changes', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $form = Livewire::actingAs($admin)->test(MedicalRecordsTable::class);
    Livewire::actingAs(User::factory()->create());
    $form->call('clearFilters')->assertForbidden();
    $form = Livewire::actingAs($admin)->test(PatientTable::class);
    Livewire::actingAs(User::factory()->create(['role' => 'Doctor']));
    $form->call('generateIdCard', 1)->assertForbidden();
});

it('uses the saved role on the next request without requiring logout', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $this->actingAs($admin)->get(route('users-roles.index'))->assertOk();
    User::whereKey($admin->getKey())->update(['role' => 'Local Health Unit Staff']);
    $this->actingAs($admin->fresh())->get(route('users-roles.index'))->assertForbidden();
    $this->get(route('patients.index'))->assertOk();
});

it('denies every module ability for inactive accounts', function (string $role) {
    $user = User::factory()->create(['role' => $role, 'is_active' => false]);
    foreach (['patient-profile-manage', 'patient-records-view', 'queue-view', 'queue-register', 'queue-consult', 'medical-records-view', 'follow-ups-manage', 'surveillance-view', 'manage-accounts'] as $ability) {
        expect(Gate::forUser($user)->allows($ability))->toBeFalse();
    }
})->with(['Admin', 'Doctor', 'Local Health Unit Staff']);
