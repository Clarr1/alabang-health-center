<?php

use App\Http\Controllers\PatientCardController;
use App\Http\Controllers\PatientMedicalRecordPdfController;
use App\Http\Controllers\ProfileController;
use App\Livewire\DetectionSettings;
use App\Livewire\DiagnosisManagement;
use App\Livewire\EditPatient;
use App\Livewire\FamilyRecord;
use App\Livewire\FamilyTable;
use App\Livewire\FollowUps;
use App\Livewire\MedicalRecordDetails;
use App\Livewire\MedicalRecordsForm;
use App\Livewire\MedicalRecordsTable;
use App\Livewire\PatientInformationForm;
use App\Livewire\PatientTable;
use App\Livewire\QueueDisplay;
use App\Livewire\QueueManagement;
use App\Livewire\SyndromeManagement;
use App\Livewire\SyndromicSurveillance;
use App\Livewire\UsersRoles;
use App\Livewire\VitalSignsForm;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/syndromic-surveillance', SyndromicSurveillance::class)->middleware('can:surveillance-view')->name('surveillance.index');
    Route::get('/syndromic-surveillance/alerts', SyndromicSurveillance::class)->middleware('can:surveillance-view')->name('surveillance.alerts');
    Route::get('/syndromic-surveillance/history', SyndromicSurveillance::class)->middleware('can:surveillance-view')->name('surveillance.history');
    Route::get('/syndromic-surveillance/settings', DetectionSettings::class)->middleware('can:surveillance-manage')->name('surveillance.settings');
    Route::get('/syndromic-surveillance/definitions', SyndromeManagement::class)->middleware('can:surveillance-approve')->name('surveillance.definitions');
    Route::get('/diagnoses', DiagnosisManagement::class)->middleware('can:diagnoses-manage')->name('diagnoses.index');
    Route::get('/follow-ups', FollowUps::class)->middleware('can:follow-ups-manage')->name('follow-ups.index');
    Route::get('/users-roles', UsersRoles::class)->middleware('can:manage-accounts')->name('users-roles.index');
    Route::get('/queues', QueueManagement::class)->middleware('can:queue-view')->name('queues.index');
    Route::get('/queues/display', QueueDisplay::class)->middleware('can:queue-view')->name('queues.display');
    Route::get('/queues/{queue_id}/vital-signs/{patient_id}', VitalSignsForm::class)->middleware('can:queue-register')->name('queues.vital-signs');
    Route::get('/queues/{queue_id}/consultation/{patient_id}', MedicalRecordsForm::class)->middleware('can:queue-consult')->name('queues.consultation');
    Route::get('/patient-information-form', PatientInformationForm::class)->middleware('can:queue-register')->name('patient.information-form');
    Route::get('/patients', PatientTable::class)->name('patients.index');
    Route::get('/patients/families', FamilyTable::class)->middleware('can:queue-view')->name('families.index');
    Route::get('/patients/families/{household}', FamilyRecord::class)->middleware('can:queue-view')->name('families.show');
    Route::get('/patients/families/{familyHouseholdId}/members/{record}', MedicalRecordDetails::class)->whereNumber('familyHouseholdId')->middleware('can:queue-view')->name('families.members.show');
    Route::get('/patients/{patient}/edit', EditPatient::class)->middleware('can:queue-register')->name('patients.edit');
    Route::get('/patients/{patient}/card', [PatientCardController::class, 'show'])->name('patients.card.show');
    Route::get('/patients/{patient}/card/pdf', [PatientCardController::class, 'download'])->name('patients.card.download');
    Route::get('/patient-vital-signs/{patient_id?}', VitalSignsForm::class)->middleware('can:queue-register')->name('patient.vital-signs-form');
    Route::get('/medical-records/records/{patient}/visits/{record}/pdf', [PatientMedicalRecordPdfController::class, 'show'])->middleware('can:queue-view')->name('medical-records.pdf');
    Route::get('/medical-records/records/{patient}/visits/{record}/download', [PatientMedicalRecordPdfController::class, 'download'])->middleware('can:queue-view')->name('medical-records.download');
    Route::get('/medical-records/records/{patient}/visits/{record}/print', [PatientMedicalRecordPdfController::class, 'print'])->middleware('can:queue-view')->name('medical-records.print');
    Route::get('/medical-records/records/{record}', MedicalRecordDetails::class)->middleware('can:queue-view')->name('medical-records.show');
    Route::get('/medical-records/{patient_id?}', MedicalRecordsTable::class)->middleware('can:queue-view')->name('medical-records.index');
    Route::get('/doctors-consultation', MedicalRecordsForm::class)->middleware('can:queue-consult')->name('doctors-consultation.index');
    Route::redirect('/medical-form/{patient_id?}', '/doctors-consultation')->middleware('can:queue-consult')->name('medical-form.index');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
