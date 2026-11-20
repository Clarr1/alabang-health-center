<?php

use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\SurveillanceAudit;
use App\Models\User;
use App\Models\VitalSign;
use App\Services\SyndromeClassifier;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PatientInformationSeeder;
use Database\Seeders\SyndromicEarlyWarningSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

it('seeds the complete desktop dataset safely and preserves it on reruns', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20T06:00:00Z'));
    Http::preventStrayRequests();
    Mail::fake();
    Notification::fake();
    Queue::fake();
    $existing = User::factory()->create();
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@gmail.com')->sole();
    expect($admin->role)->toBe('Admin')->and($admin->is_active)->toBeTrue()
        ->and(Hash::check('admin', $admin->password))->toBeTrue()
        ->and(User::where('email', 'staff@example.test')->sole()->role)->toBe('Local Health Unit Staff')
        ->and(User::where('email', 'doctor@example.test')->sole()->role)->toBe('Doctor');
    $this->post('/login', ['email' => 'admin@gmail.com', 'password' => 'admin', 'account_type' => 'Admin'])->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($admin);

    $patients = PatientInformation::where('address', 'like', PatientInformationSeeder::ADDRESS_PREFIX.'%')->get();
    expect($patients)->toHaveCount(10);
    foreach ($patients as $patient) {
        expect($patient->surveillance_residence)->toBe('Alabang')
            ->and($patient->residence_verified_at)->not->toBeNull()
            ->and($patient->registrationProfile)->not->toBeNull()
            ->and($patient->sms_consent)->toBeFalse()
            ->and($patient->vitalSigns()->count())->toBe(1)
            ->and($patient->medicalRecords()->count())->toBe(1);
        $record = $patient->medicalRecords()->sole();
        expect(app(SyndromeClassifier::class)->isValid($record))->toBeTrue()
            ->and($record->symptoms)->toHaveCount(2)
            ->and((float) $record->vitalSign->bmi)->toBeGreaterThan(0);
    }
    $manifest = SurveillanceAudit::where('action', SyndromicEarlyWarningSeeder::MARKER)->sole()->details;
    expect(PatientInformation::count())->toBe($manifest['patients'] + 10)
        ->and(MedicalRecord::count())->toBe($manifest['consultations'] + 10)
        ->and(VitalSign::count())->toBe($manifest['consultations'] + 10);
    $snapshots = [];
    foreach (['users', 'patient_information', 'patient_profiles', 'vital_signs', 'medical_records', 'medical_record_symptoms'] as $table) {
        $snapshots[$table] = DB::table($table)->get()->toJson();
    }
    $this->travel(1)->days();
    $this->seed(DatabaseSeeder::class);
    foreach ($snapshots as $table => $snapshot) {
        expect(DB::table($table)->get()->toJson())->toBe($snapshot);
    }
    expect($existing->fresh()->email)->toBe($existing->email);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('rejects default development seeding in production without writes', function () {
    app()->detectEnvironment(fn (): string => 'production');
    try {
        expect(fn () => app(DatabaseSeeder::class)->run())->toThrow(RuntimeException::class, 'local or testing');
        expect(User::count())->toBe(0)->and(PatientInformation::count())->toBe(0);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

it('does not replace an existing account with a different role', function () {
    $user = User::factory()->create(['email' => 'admin@gmail.com', 'role' => 'Doctor']);
    expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow(RuntimeException::class, 'incompatible');
    expect($user->fresh()->role)->toBe('Doctor')->and(PatientInformation::count())->toBe(0);
});
