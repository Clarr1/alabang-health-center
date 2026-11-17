<?php

use App\Livewire\SyndromicSurveillance;
use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\SurveillanceAlert;
use App\Models\SurveillanceAudit;
use App\Models\SurveillanceClassification;
use App\Models\SurveillanceRun;
use App\Models\SurveillanceSetting;
use App\Models\Symptom;
use App\Models\SyndromeCategory;
use App\Models\User;
use App\Services\SurveillanceEngine;
use App\Services\SyndromeClassifier;
use Carbon\CarbonImmutable;
use Database\Factories\MedicalRecordFactory;
use Database\Factories\PatientInformationFactory;
use Database\Seeders\SyndromicEarlyWarningSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('seeds rerunnable historical workflows and calculates alerts through the real engine', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 06:00:00', 'UTC'));
    Http::preventStrayRequests();
    Mail::fake();
    Notification::fake();
    Queue::fake();
    config(['textbee.enabled' => true]);
    $admin = User::factory()->create(['role' => 'Admin']);
    $this->actingAs($admin);
    $existingPatient = PatientInformationFactory::new()->create();
    $patientBefore = $existingPatient->refresh()->getAttributes();
    $settings = SurveillanceSetting::current();
    $this->seed(SyndromicEarlyWarningSeeder::class);
    $manifest = SurveillanceAudit::where('action', SyndromicEarlyWarningSeeder::MARKER)->sole()->details;
    expect($manifest['patients'])->toBe(2122)->and($manifest['consultations'])->toBe(2123)
        ->and(PatientInformation::count())->toBe(2123)->and(MedicalRecord::count())->toBe(2123)
        ->and(SurveillanceAlert::count())->toBe(0)->and(SurveillanceRun::count())->toBe(0)
        ->and(SurveillanceClassification::count())->toBe(0)
        ->and(auth()->id())->toBe($admin->getKey())->and(config('textbee.enabled'))->toBeTrue()
        ->and($existingPatient->fresh()->getAttributes())->toBe($patientBefore)
        ->and(SurveillanceSetting::current())->toBe(array_replace($settings, ['settings_reviewed' => true]));
    expect(MedicalRecord::whereHas('patient', fn ($query) => $query->where('surveillance_residence', '!=', 'Alabang')->orWhereNull('residence_verified_at')->orWhereNotNull('contact_number')->orWhere('sms_consent', true))->exists())->toBeFalse();
    $classifier = app(SyndromeClassifier::class);
    foreach (MedicalRecord::with('queue', 'vitalSign', 'patient.registrationProfile', 'symptoms', 'primaryDiagnosis')->get() as $record) {
        expect($classifier->isValid($record))->toBeTrue()->and($record->symptoms)->not->toBeEmpty()
            ->and($record->primaryDiagnosis->name)->toBe($record->diagnosis)
            ->and($record->patient->registrationProfile)->not->toBeNull();
    }
    $snapshot = [];
    foreach (['patient_information', 'patient_profiles', 'queues', 'queue_days', 'vital_signs', 'medical_records', 'medical_record_symptoms', 'users', 'symptoms', 'diagnoses', 'syndrome_rule_versions', 'surveillance_events'] as $table) {
        $snapshot[$table] = hash('sha256', DB::table($table)->get()->toJson());
    }
    $this->travel(1)->days();
    $this->seed(SyndromicEarlyWarningSeeder::class);
    foreach ($snapshot as $table => $rows) {
        expect(hash('sha256', DB::table($table)->get()->toJson()))->toBe($rows);
    }
    $this->travelTo(CarbonImmutable::parse($manifest['anchor']));
    foreach ($manifest['windows'] as $name => $window) {
        $this->artisan('surveillance:detect', ['--at' => $window['end']])->assertSuccessful();
        $run = SurveillanceRun::latest('id')->firstOrFail();
        expect($run->status)->toBe('Completed')->and($run->window_start->diffInHours($run->window_end))->toEqual(48);
        foreach ($run->results as $result) {
            $expected = $name === 'Combined current spike'
                ? (in_array($result->syndrome_name, ['SEW TEST Dengue-like', 'SEW TEST Chickenpox-like', 'SEW TEST Malaria-like'], true) ? 'Alert' : 'Normal')
                : ($result->syndrome_name === 'SEW TEST '.$name ? 'Alert' : 'Normal');
            expect($result->outcome)->toBe($expected)->and(count($result->calculation['baseline_periods']))->toBe(12)
                ->and($result->calculation['current_coverage'])->toEqual(1);
        }
        unset($result, $run);
        gc_collect_cycles();
    }
    expect(SurveillanceAlert::count())->toBe(6);
    $run = SurveillanceRun::latest('id')->firstOrFail();
    $result = $run->results->firstWhere('syndrome_name', 'SEW TEST Dengue-like');
    expect($result->observed)->toBe(43)->and($run->eligible_cases)->toBe(161);
    $end = CarbonImmutable::parse($manifest['anchor']);
    $inside = MedicalRecord::where('encounter_at', $end->subHours(48))->sole();
    $outside = MedicalRecord::where('encounter_at', $end->subHours(49))->sole();
    expect($result->cases->pluck('medical_record_id')->all())->toContain($inside->getKey())->not->toContain($outside->getKey());
    $repeat = MedicalRecord::where('encounter_at', $end->subHour())->sole();
    expect($result->cases->pluck('medical_record_id')->all())->not->toContain($repeat->getKey());
    $this->artisan('surveillance:detect')->assertSuccessful();
    expect(SurveillanceAlert::count())->toBe(6);
    foreach (['surveillance.index', 'surveillance.alerts', 'surveillance.history'] as $route) {
        $this->actingAs($admin)->get(route($route))->assertOk()->assertSee('SEW TEST Dengue-like')->assertSee('SEW TEST Chickenpox-like')->assertSee('SEW TEST Malaria-like');
    }
    Livewire::actingAs($admin)->test(SyndromicSurveillance::class)
        ->call('openAlert', SurveillanceAlert::latest('id')->firstOrFail()->id)
        ->assertSee('Counted illness episodes')->assertSee('SEW TEST Patient')->assertHasNoErrors();
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('rolls back the whole fixture when an existing catalog entry is inactive', function () {
    $symptom = Symptom::where('name', 'Fever')->sole();
    $symptom->update(['is_active' => false]);
    expect(fn () => $this->seed(SyndromicEarlyWarningSeeder::class))->toThrow(RuntimeException::class, 'inactive');
    expect(PatientInformation::count())->toBe(0)->and(User::count())->toBe(0)
        ->and(SyndromeCategory::count())->toBe(0)->and($symptom->fresh()->is_active)->toBeFalse();
});

it('rejects production seeding before inserting records', function () {
    app()->detectEnvironment(fn (): string => 'production');
    try {
        expect(fn () => app(SyndromicEarlyWarningSeeder::class)->run())->toThrow(RuntimeException::class, 'local or testing');
        expect(PatientInformation::count())->toBe(0)->and(User::count())->toBe(0);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

it('rejects invalid or future replay dates and scheduled replays', function (array $options) {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 06:00:00', 'UTC'));
    $this->artisan('surveillance:detect', $options)->assertFailed();
    expect(SurveillanceRun::count())->toBe(0);
})->with([
    'relative date' => [['--at' => 'yesterday']],
    'invalid calendar date' => [['--at' => '2026-02-30T00:00:00Z']],
    'future date' => [['--at' => '2027-01-01T00:00:00Z']],
    'scheduled replay' => [['--at' => '2026-11-15T00:00:00Z', '--scheduled' => true]],
]);

it('honors the upper exclusive window boundary when replaying encounters', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 06:00:00', 'UTC'));
    $end = CarbonImmutable::now('UTC');
    $record = MedicalRecordFactory::new()->create(['encounter_at' => $end]);
    $record->patient->update(['surveillance_residence' => 'Alabang']);
    $run = app(SurveillanceEngine::class)->run('console', $end);
    expect($run->eligible_cases)->toBe(0);

    $record->update(['encounter_at' => $end->subMinute()]);
    expect(app(SurveillanceEngine::class)->run('console', $end)->eligible_cases)->toBe(1);
});
