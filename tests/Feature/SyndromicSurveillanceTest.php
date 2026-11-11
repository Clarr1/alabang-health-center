<?php

use App\Livewire\MedicalRecordsForm;
use App\Livewire\SyndromeManagement;
use App\Livewire\SyndromicSurveillance;
use App\Models\MedicalRecord;
use App\Models\Queue;
use App\Models\SurveillanceAlert;
use App\Models\SurveillanceClassification;
use App\Models\SurveillanceResult;
use App\Models\SurveillanceRun;
use App\Models\SurveillanceSetting;
use App\Models\Symptom;
use App\Models\SyndromeCategory;
use App\Models\SyndromeRuleVersion;
use App\Models\User;
use App\Services\SurveillanceAdministration;
use App\Services\SurveillanceEngine;
use App\Services\SurveillanceReview;
use App\Services\SyndromeClassifier;
use Carbon\CarbonImmutable;
use Database\Factories\MedicalRecordFactory;
use Database\Factories\PatientInformationFactory;
use Database\Factories\VitalSignFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function simulatedSurveillanceRule(): SyndromeRuleVersion
{
    $admin = User::factory()->create(['role' => 'Admin']);
    test()->actingAs($admin);
    $rule = app(SurveillanceAdministration::class)->saveDefinition([
        'name' => 'SIMULATED Febrile', 'description' => 'Test-only definition', 'is_active' => true,
        'symptom_ids' => [Symptom::where('name', 'Fever')->value('id')], 'minimum_matches' => 1,
        'applies_from' => now('Asia/Manila')->subWeeks(14)->toDateString(), 'rationale' => 'SIMULATED definition for isolated automated tests only.',
    ]);
    app(SurveillanceAdministration::class)->approve($rule->id);

    return $rule->refresh();
}

function simulatedSurveillanceRecord(CarbonImmutable $time, string $symptom = 'Fever', array $attributes = []): MedicalRecord
{
    $patient = PatientInformationFactory::new()->create(['first_name' => 'SIMULATED', 'surveillance_residence' => 'Alabang']);
    $doctor = User::where('role', 'Doctor')->first() ?? User::factory()->create(['role' => 'Doctor']);
    $vital = VitalSignFactory::new()->create(['patient_id' => $patient->patient_id, 'recorded_by' => $doctor->user_id]);
    $record = MedicalRecordFactory::new()->create(array_merge([
        'vital_signs_id' => $vital->vital_id, 'handled_by' => $doctor->user_id,
        'patient_id' => $patient->patient_id, 'consultation_date' => $time->setTimezone('Asia/Manila')->toDateString(),
        'encounter_at' => $time, 'created_at' => $time, 'diagnosis' => 'SIMULATED clinician wording',
    ], $attributes));
    $catalog = Symptom::where('name', $symptom)->firstOrFail();
    $record->symptoms()->attach($catalog->id, ['symptom_name' => $catalog->name]);

    return $record;
}

it('classifies approved structured symptoms once and preserves snapshots after rule changes', function () {
    $rule = simulatedSurveillanceRule();
    $record = simulatedSurveillanceRecord(CarbonImmutable::now('UTC')->subHour());
    $classifier = app(SyndromeClassifier::class);
    expect($classifier->classify($record)->sole()->matches)->toBeTrue();
    $classifier->classify($record);
    expect(SurveillanceClassification::count())->toBe(1)->and($record->fresh()->diagnosis)->toBe('SIMULATED clinician wording');
    $second = app(SurveillanceAdministration::class)->saveDefinition([
        'name' => 'SIMULATED Febrile', 'description' => null, 'is_active' => true,
        'symptom_ids' => [Symptom::where('name', 'Cough')->value('id')], 'minimum_matches' => 1,
        'applies_from' => now('Asia/Manila')->subWeeks(14)->toDateString(), 'rationale' => 'SIMULATED revised definition for regression testing.',
    ], $rule->syndrome_category_id);
    app(SurveillanceAdministration::class)->approve($second->id);
    expect($classifier->classify($record)->sole()->syndrome_rule_version_id)->toBe($rule->id);
    $newRecord = simulatedSurveillanceRecord(CarbonImmutable::now('UTC')->subMinutes(30));
    expect($classifier->classify($newRecord)->sole()->matches)->toBeFalse();
});

it('ignores free text unreviewed definitions and inactive categories', function () {
    $rule = simulatedSurveillanceRule();
    $unrelated = simulatedSurveillanceRecord(CarbonImmutable::now('UTC')->subHour(), 'Cough', ['chief_complaint' => 'Fever fever fever']);
    expect(app(SyndromeClassifier::class)->classify($unrelated)->sole()->matches)->toBeFalse();
    SyndromeCategory::findOrFail($rule->syndrome_category_id)->update(['is_active' => false]);
    expect(app(SyndromeClassifier::class)->classify(simulatedSurveillanceRecord(CarbonImmutable::now('UTC')->subHour())))->toBeEmpty();
});

function simulatedSurveillanceBaseline(int $currentCount = 10): SyndromeRuleVersion
{
    test()->travelTo(CarbonImmutable::parse('2026-11-09 10:00:00', 'UTC'));
    $rule = simulatedSurveillanceRule();
    $settings = SurveillanceSetting::current();
    $settings['settings_reviewed'] = true;
    app(SurveillanceAdministration::class)->saveSettings($settings);
    for ($week = 1; $week <= 8; $week++) {
        for ($index = 0; $index < 5; $index++) {
            simulatedSurveillanceRecord(CarbonImmutable::now('UTC')->subWeeks($week)->subHours(2)->subMinutes($index), $index === 0 ? 'Fever' : 'Cough');
        }
    }
    for ($index = 0; $index < max(5, $currentCount); $index++) {
        simulatedSurveillanceRecord(CarbonImmutable::now('UTC')->subHours(2)->subMinutes($index), $index < $currentCount ? 'Fever' : 'Cough');
    }

    return $rule;
}

it('generates explainable internal alerts from historical episodes without external notifications and deduplicates reruns', function () {
    Mail::fake();
    Notification::fake();
    Http::preventStrayRequests();
    Http::fake();
    simulatedSurveillanceBaseline();
    $engine = app(SurveillanceEngine::class);
    $run = $engine->run();
    $result = $run->results()->sole();
    expect($run->status)->toBe('Completed')->and($run->eligible_cases)->toBe(10)
        ->and($result->outcome)->toBe('Alert')->and($result->observed)->toBe(10)
        ->and($result->expected)->toBe(2.0)->and($result->model)->toBe('Poisson')
        ->and($result->cases()->count())->toBe(10)->and($result->calculation['baseline_periods'])->toHaveCount(8)
        ->and($result->reason)->toContain('does not confirm an outbreak');
    $alert = SurveillanceAlert::sole();
    expect($alert->scope)->toBe('Barangay Alabang, Muntinlupa City')->and($alert->status)->toBe('New');
    $engine->run();
    $this->travel(1)->minutes();
    $engine->run();
    expect(SurveillanceAlert::count())->toBe(1)->and(SurveillanceRun::count())->toBe(3);
    $this->get(route('surveillance.index'))->assertOk()->assertSee('10 / 2.00')->assertSee('Surveillance: 1 new');
    Livewire::test(SyndromicSurveillance::class)->call('openAlert', $alert->id)
        ->assertSee('Counted illness episodes')->assertSee('SIMULATED clinician wording');
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
    Http::assertNothingSent();
});

it('keeps normal counts normal and prevents multiple testing from amplifying marginal signals', function () {
    $rule = simulatedSurveillanceBaseline(1);
    $engine = app(SurveillanceEngine::class);
    expect($engine->run()->results()->sole()->outcome)->toBe('Normal')->and(SurveillanceAlert::count())->toBe(0);
    for ($index = 0; $index < 3; $index++) {
        simulatedSurveillanceRecord(CarbonImmutable::now('UTC')->subHour()->subMinutes($index));
    }
    $settings = SurveillanceSetting::current();
    $settings['alpha'] = 0.04;
    $settings['watch_alpha'] = 0.1;
    app(SurveillanceAdministration::class)->saveSettings($settings);
    $single = $engine->run()->results()->sole();
    $copy = app(SurveillanceAdministration::class)->saveDefinition([
        'name' => 'SIMULATED second category', 'description' => null, 'is_active' => true,
        'symptom_ids' => [Symptom::where('name', 'Fever')->value('id')], 'minimum_matches' => 1,
        'applies_from' => now('Asia/Manila')->subWeeks(14)->toDateString(), 'rationale' => 'SIMULATED multiplicity comparison definition.',
    ]);
    app(SurveillanceAdministration::class)->approve($copy->id);
    $multiple = $engine->run()->results()->where('syndrome_category_id', $rule->syndrome_category_id)->sole();
    expect($multiple->adjusted_probability)->toEqualWithDelta(min(1, 2 * $single->probability), 1e-12)
        ->and($multiple->calculation['family_size'])->toBe(2)
        ->and($single->outcome)->toBe('Watch')->and($multiple->outcome)->toBe('Normal');
});

it('excludes unverified outside canceled invalid and out of window encounters and merges repeat illness visits', function () {
    $rule = simulatedSurveillanceBaseline(1);
    $time = CarbonImmutable::now('UTC')->subHour();
    $outside = simulatedSurveillanceRecord($time);
    $outside->patient->update(['surveillance_residence' => 'Outside Scope']);
    $unverified = simulatedSurveillanceRecord($time);
    $unverified->patient->update(['surveillance_residence' => 'Unverified']);
    $legacy = simulatedSurveillanceRecord($time, 'Fever', ['encounter_at' => null, 'consultation_date' => $time->subDays(5)->toDateString()]);
    simulatedSurveillanceRecord($time->subHours(49));
    simulatedSurveillanceRecord(CarbonImmutable::now('UTC'));
    $cancelled = simulatedSurveillanceRecord($time);
    $queue = Queue::factory()->create(['patient_id' => $cancelled->patient_id, 'status' => 'Cancelled']);
    $cancelled->vitalSign->update(['queue_id' => $queue->getKey()]);
    $cancelled->update(['queue_id' => $queue->getKey()]);
    $original = MedicalRecord::where('encounter_at', '>=', CarbonImmutable::now('UTC')->subHours(48))->where('diagnosis', 'SIMULATED clinician wording')->whereHas('symptoms', fn ($query) => $query->where('name', 'Fever'))->firstOrFail();
    $repeat = MedicalRecordFactory::new()->create([
        'patient_id' => $original->patient_id, 'vital_signs_id' => $original->vital_signs_id, 'handled_by' => $original->handled_by,
        'encounter_at' => $time, 'consultation_date' => $time->setTimezone('Asia/Manila')->toDateString(), 'created_at' => $time,
    ]);
    $repeat->symptoms()->attach(Symptom::where('name', 'Fever')->value('id'), ['symptom_name' => 'Fever']);
    $result = app(SurveillanceEngine::class)->run()->results()->sole();
    expect($result->observed)->toBe(1)->and($result->cases()->count())->toBe(1)
        ->and($result->calculation['current_attendance'])->toBe(6)
        ->and($legacy->fresh()->encounter_at)->toBeNull();
});

it('reports insufficient data for unreliable coverage unreviewed settings and zero baselines', function () {
    simulatedSurveillanceRule();
    simulatedSurveillanceRecord(CarbonImmutable::now('UTC')->subHour());
    $engine = app(SurveillanceEngine::class);
    $result = $engine->run()->results()->sole();
    expect($result->outcome)->toBe('Insufficient Data')->and($result->probability)->toBeNull()
        ->and($result->reason)->toContain('review the statistical configuration')
        ->and(SurveillanceAlert::count())->toBe(0);
    $settings = SurveillanceSetting::current();
    $settings['settings_reviewed'] = true;
    app(SurveillanceAdministration::class)->saveSettings($settings);
    expect($engine->run()->results()->sole()->outcome)->toBe('Insufficient Data');
});

it('preserves review history rejects stale reviews and enforces backend roles', function () {
    simulatedSurveillanceBaseline();
    app(SurveillanceEngine::class)->run();
    $alert = SurveillanceAlert::sole();
    $doctor = User::where('role', 'Doctor')->firstOrFail();
    $this->actingAs($doctor);
    $review = app(SurveillanceReview::class);
    $review->record($alert->id, 1, 'Under Review', 'SIMULATED investigation started', null, $doctor->getKey());
    expect(fn () => $review->record($alert->id, 1, 'Resolved', 'SIMULATED stale action', 'Reviewed simulated data.', null))->toThrow(ValidationException::class);
    $review->record($alert->id, 2, 'Resolved', 'SIMULATED investigation complete', 'No outbreak confirmation inferred.', $doctor->getKey());
    expect($alert->fresh()->reviews()->count())->toBe(2)->and($alert->fresh()->status)->toBe('Resolved');
    $this->get(route('surveillance.index'))->assertOk()->assertSee('Surveillance: 0 new');
    $staff = User::factory()->create(['role' => 'Local Health Unit Staff']);
    Livewire::actingAs($staff)->test(SyndromicSurveillance::class)
        ->assertDontSee('SIMULATED clinician wording')->call('openAlert', $alert->id)->assertForbidden();
    $this->actingAs($staff)->get(route('surveillance.definitions'))->assertForbidden();
    expect(fn () => $review->record($alert->id, 3, 'Under Review', 'Unauthorized simulated change', null, null))->toThrow(AuthorizationException::class);
    expect($alert->fresh()->reviews()->count())->toBe(2);
});

it('renders definitions enforces manual detection access and validates administrative input', function () {
    simulatedSurveillanceRule();
    $this->get(route('surveillance.definitions'))->assertOk()->assertSee('Approved')->assertSee('Fever');
    Livewire::test(SyndromeManagement::class)->set('settings.alpha', 0.9)
        ->call('saveSettings')->assertHasErrors('alpha');
    $doctor = User::factory()->create(['role' => 'Doctor']);
    Livewire::actingAs($doctor)->test(SyndromicSurveillance::class)->call('detect')->assertForbidden();
    Livewire::actingAs($doctor)->test(SyndromeManagement::class)->call('saveDefinition')->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'Doctor', 'is_active' => false]))->get(route('surveillance.index'))->assertForbidden();
});

it('records a failed execution and rolls back partial classifications and results', function () {
    simulatedSurveillanceBaseline();
    DB::statement("CREATE TRIGGER fail_surveillance BEFORE INSERT ON surveillance_results BEGIN SELECT RAISE(ABORT, 'SIMULATED detection storage failure'); END");
    try {
        expect(fn () => app(SurveillanceEngine::class)->run())->toThrow(QueryException::class);
    } finally {
        DB::statement('DROP TRIGGER fail_surveillance');
    }
    expect(SurveillanceRun::sole()->status)->toBe('Failed')
        ->and(SurveillanceResult::count())->toBe(0)->and(SurveillanceClassification::count())->toBe(0)
        ->and(SurveillanceAlert::count())->toBe(0);
    expect(app(SurveillanceEngine::class)->run()->status)->toBe('Completed');
});

it('runs the scheduler command independently of page visits and respects the configured interval', function () {
    simulatedSurveillanceBaseline();
    $this->artisan('surveillance:detect --scheduled')->assertSuccessful();
    $this->artisan('surveillance:detect --scheduled')->assertSuccessful();
    expect(SurveillanceRun::count())->toBe(1)->and(SurveillanceRun::sole()->source)->toBe('scheduled');
    $this->travel(60)->minutes();
    $this->artisan('surveillance:detect --scheduled')->assertSuccessful();
    expect(SurveillanceRun::count())->toBe(2)->and(SurveillanceAlert::count())->toBe(1);
});

it('counts reliable legacy timestamps includes the exact window start and excludes the exact window end', function () {
    simulatedSurveillanceBaseline(1);
    $start = CarbonImmutable::now('UTC')->subHours(48);
    $included = simulatedSurveillanceRecord($start, 'Fever', ['encounter_at' => null]);
    simulatedSurveillanceRecord($start->subSecond());
    simulatedSurveillanceRecord(CarbonImmutable::now('UTC'));
    $result = app(SurveillanceEngine::class)->run()->results()->sole();
    expect($result->observed)->toBe(2)->and($result->cases->pluck('medical_record_id'))->toContain($included->getKey());
});

it('does not use unapproved definitions or multiple symptoms to inflate a syndrome count', function () {
    $rule = simulatedSurveillanceRule();
    $category = SyndromeCategory::findOrFail($rule->syndrome_category_id);
    $newRule = app(SurveillanceAdministration::class)->saveDefinition([
        'name' => $category->name, 'description' => null, 'is_active' => true,
        'symptom_ids' => Symptom::whereIn('name', ['Fever', 'Cough'])->pluck('id')->all(), 'minimum_matches' => 1,
        'applies_from' => now('Asia/Manila')->subWeeks(14)->toDateString(), 'rationale' => 'SIMULATED multiple structured symptom mappings.',
    ], $category->id);
    $record = simulatedSurveillanceRecord(CarbonImmutable::now('UTC')->subHour(), 'Cough');
    expect(app(SyndromeClassifier::class)->classify($record)->sole()->matches)->toBeFalse();
    app(SurveillanceAdministration::class)->approve($newRule->id);
    $record = simulatedSurveillanceRecord(CarbonImmutable::now('UTC')->subHour());
    $record->symptoms()->attach(Symptom::where('name', 'Cough')->value('id'), ['symptom_name' => 'Cough']);
    expect(app(SyndromeClassifier::class)->classify($record)->sole()->matches)->toBeTrue()
        ->and(SurveillanceClassification::where('medical_record_id', $record->getKey())->count())->toBe(1);
});

it('requires adequate current coverage and a nonzero historical rate rather than treating missing data as zero cases', function () {
    simulatedSurveillanceBaseline(1);
    $engine = app(SurveillanceEngine::class);
    $run = $engine->run();
    expect($run->results()->sole()->outcome)->toBe('Normal');
    $historical = MedicalRecord::where('encounter_at', '<', CarbonImmutable::now('UTC')->subDays(3))->pluck('medical_record_id');
    SurveillanceClassification::whereIn('medical_record_id', $historical)->update(['matches' => false]);
    $zero = $engine->run()->results()->sole();
    expect($zero->outcome)->toBe('Insufficient Data')->and($zero->reason)->toContain('no syndrome episodes');
    for ($index = 0; $index < 10; $index++) {
        $record = simulatedSurveillanceRecord(CarbonImmutable::now('UTC')->subHour());
        $record->symptoms()->detach();
    }
    expect($engine->run()->results()->sole()->reason)->toContain('structured-symptom coverage');
});

it('skips overlapping detections and preserves immutable evidence referenced by alerts', function () {
    simulatedSurveillanceBaseline();
    $lock = Cache::lock('syndromic-surveillance-detection', 3600);
    $lock->get();
    try {
        expect(app(SurveillanceEngine::class)->run()->status)->toBe('Skipped');
    } finally {
        $lock->release();
    }
    app(SurveillanceEngine::class)->run();
    $alert = SurveillanceAlert::sole();
    $case = $alert->result->cases()->firstOrFail();
    expect(fn () => $case->delete())->toThrow(QueryException::class);
    expect(fn () => $case->record->delete())->toThrow(QueryException::class);
    expect($alert->fresh()->result->cases()->count())->toBe(10);
});

it('can run without approved definitions and retains an administrative execution record', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $this->actingAs($admin);
    SyndromeCategory::factory()->create();
    $run = app(SurveillanceEngine::class)->run();
    expect($run->status)->toBe('Completed')->and($run->results()->sole()->outcome)->toBe('Insufficient Data');
    $this->get(route('surveillance.index'))->assertOk()->assertSee('Insufficient Data');
});

it('excludes legacy intakes explicitly marked synthetic even when the patient residence is verified', function () {
    simulatedSurveillanceBaseline(1);
    $record = MedicalRecord::where('encounter_at', '>=', CarbonImmutable::now('UTC')->subHours(48))
        ->whereHas('symptoms', fn ($query) => $query->where('name', 'Fever'))->firstOrFail();
    $queue = Queue::factory()->create(['patient_id' => $record->patient_id, 'status' => 'Completed']);
    $record->vitalSign->update(['queue_id' => $queue->getKey()]);
    $record->update(['queue_id' => $queue->getKey()]);
    Schema::create('surveillance_intakes', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('queue_id');
        $table->boolean('is_synthetic');
    });
    DB::table('surveillance_intakes')->insert(['queue_id' => $queue->getKey(), 'is_synthetic' => true]);
    try {
        expect(app(SurveillanceEngine::class)->run()->results()->sole()->observed)->toBe(0);
    } finally {
        Schema::drop('surveillance_intakes');
    }
});

it('captures classifications during completed structured consultations without changing the diagnosis', function () {
    simulatedSurveillanceRule();
    $doctor = User::factory()->create(['role' => 'Doctor']);
    $queue = Queue::factory()->create(['status' => 'Ready for Consultation']);
    $queue->patient->update(['surveillance_residence' => 'Alabang']);
    VitalSignFactory::new()->create(['patient_id' => $queue->patient_id, 'queue_id' => $queue->getKey(), 'recorded_by' => $doctor->getKey()]);
    Livewire::actingAs($doctor)->test(MedicalRecordsForm::class)->call('startConsultation', $queue->getKey())
        ->call('selectSymptom', Symptom::where('name', 'Fever')->value('id'))
        ->set('diagnosis', 'SIMULATED clinical diagnosis')->set('treatment', 'SIMULATED treatment')
        ->set('consultation_notes', 'SIMULATED clinical notes')->call('saveMedicalRecord')->assertHasNoErrors();
    $record = MedicalRecord::sole();
    expect($record->encounter_at)->not->toBeNull()->and($record->diagnosis)->toBe('SIMULATED clinical diagnosis')
        ->and(SurveillanceClassification::sole()->matches)->toBeTrue()->and($queue->fresh()->status)->toBe('Completed');
});

it('requires verified residence and clears verification when the address changes', function () {
    simulatedSurveillanceRule();
    $patient = PatientInformationFactory::new()->create();
    expect($patient->fresh()->surveillance_residence)->toBe('Unverified')->and($patient->residence_verified_at)->toBeNull();
    $patient->update(['surveillance_residence' => 'Alabang']);
    expect($patient->fresh()->residence_verified_by)->toBe(auth()->id())->and($patient->residence_verified_at)->not->toBeNull();
    $patient->update(['address' => 'SIMULATED changed address']);
    expect($patient->fresh()->surveillance_residence)->toBe('Unverified')->and($patient->residence_verified_at)->toBeNull();
});

it('deduplicates an overlapping investigation even when a later disjoint investigation exists', function () {
    simulatedSurveillanceBaseline();
    $originalEnd = CarbonImmutable::now('UTC');
    $engine = app(SurveillanceEngine::class);
    $engine->run();
    $this->travel(7)->days();
    for ($index = 0; $index < 15; $index++) {
        simulatedSurveillanceRecord(CarbonImmutable::now('UTC')->subHours(2)->subMinutes($index));
    }
    $engine->run();
    expect(SurveillanceAlert::count())->toBe(2);
    simulatedSurveillanceRecord($originalEnd->addMinutes(20));
    $result = $engine->run('manual', $originalEnd->addMinutes(30))->results()->sole();
    expect($result->outcome)->toBe('Alert')->and(SurveillanceAlert::count())->toBe(2);
});

it('keeps the eligible-patient total for the full window when a definition starts partway through it', function () {
    test()->travelTo(CarbonImmutable::parse('2026-11-09 10:00:00', 'UTC'));
    $admin = User::factory()->create(['role' => 'Admin']);
    test()->actingAs($admin);
    $rule = app(SurveillanceAdministration::class)->saveDefinition([
        'name' => 'SIMULATED new category', 'description' => null, 'is_active' => true,
        'symptom_ids' => [Symptom::where('name', 'Fever')->value('id')], 'minimum_matches' => 1,
        'applies_from' => now('Asia/Manila')->toDateString(), 'rationale' => 'SIMULATED definition beginning today only.',
    ]);
    app(SurveillanceAdministration::class)->approve($rule->id);
    simulatedSurveillanceRecord(CarbonImmutable::now('UTC')->subHours(24));
    simulatedSurveillanceRecord(CarbonImmutable::now('UTC')->subHour());
    $run = app(SurveillanceEngine::class)->run();
    expect($run->eligible_cases)->toBe(2)->and($run->results()->sole()->observed)->toBe(1);
});
