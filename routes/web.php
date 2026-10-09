<?php

use App\Http\Controllers\PatientCardController;
use App\Http\Controllers\ProfileController;
use App\Livewire\MedicalRecordsForm;
use App\Livewire\PatientInformationForm;
use App\Livewire\PatientTable;
use App\Livewire\VitalSignsForm;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/patient-information-form', PatientInformationForm::class)->name('patient.information-form');
    Route::get('/patients', PatientTable::class)->name('patients.index');
    Route::get('/patients/{patient}/card', [PatientCardController::class, 'show'])->name('patients.card.show');
    Route::get('/patients/{patient}/card/pdf', [PatientCardController::class, 'download'])->name('patients.card.download');
    Route::get('/patient-vital-signs/{patient_id?}', VitalSignsForm::class)->name('patient.vital-signs-form');
    Route::get('/medical-records/{patient_id?}', MedicalRecordsForm::class)->name('medical-records.index');
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
