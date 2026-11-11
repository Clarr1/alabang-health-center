<?php

use App\Models\MedicalRecord;
use App\Models\PatientInformation;
use App\Models\SurveillanceAlert;
use App\Models\SurveillanceRun;
use App\Models\User;
use Database\Seeders\SurveillanceDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

it('creates visibly fictional scenarios with real calculated outcomes and no external notifications', function () {
    Http::preventStrayRequests();
    Http::fake();
    Mail::fake();
    Notification::fake();
    config(['textbee.enabled' => false]);
    $this->seed(SurveillanceDemoSeeder::class);
    expect(PatientInformation::count())->toBe(264)->and(MedicalRecord::count())->toBe(265)
        ->and(PatientInformation::where('first_name', '!=', 'SIMULATED')->count())->toBe(0)
        ->and(PatientInformation::whereNotNull('contact_number')->count())->toBe(0)
        ->and(SurveillanceRun::count())->toBe(5)->and(SurveillanceAlert::count())->toBe(1);
    $latest = SurveillanceRun::latest('window_end')->firstOrFail();
    $results = $latest->results->keyBy('syndrome_name');
    expect($latest->eligible_cases)->toBe(20)
        ->and($results['SIMULATED Febrile']->outcome)->toBe('Alert')->and($results['SIMULATED Febrile']->observed)->toBe(12)
        ->and($results['SIMULATED Respiratory']->outcome)->toBe('Watch')->and($results['SIMULATED Respiratory']->observed)->toBe(5)
        ->and($results['SIMULATED Gastrointestinal']->outcome)->toBe('Normal')
        ->and($results['SIMULATED Rash - Unreviewed']->outcome)->toBe('Insufficient Data');
    $admin = User::where('email', 'admin@surveillance-demo.test')->firstOrFail();
    $this->post(route('login'), ['account_type' => 'Admin', 'email' => $admin->email, 'password' => SurveillanceDemoSeeder::PASSWORD])->assertRedirect(route('dashboard', absolute: false));
    config(['surveillance.demo' => true]);
    $this->actingAs($admin)->get(route('surveillance.index'))->assertOk()->assertSee('FICTIONAL DEMO DATA')
        ->assertSee('SIMULATED Febrile')->assertSee('Watch')->assertSee('20 unique eligible patients');
    expect(fn () => $this->seed(SurveillanceDemoSeeder::class))->toThrow(RuntimeException::class, 'empty database');
    expect(PatientInformation::count())->toBe(264)->and(SurveillanceAlert::count())->toBe(1);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
    Http::assertNothingSent();
});

it('refuses fictional patient insertion into an application database', function () {
    $originalDatabase = DB::connection()->getDatabaseName();
    DB::connection()->setDatabaseName('alabang-health-center');
    try {
        expect(fn () => $this->seed(SurveillanceDemoSeeder::class))->toThrow(RuntimeException::class, 'separate ahc_surveillance_demo_');
    } finally {
        DB::connection()->setDatabaseName($originalDatabase);
    }
    expect(PatientInformation::count())->toBe(0)->and(User::count())->toBe(0);
});

it('restores saved local configuration without deleting the separate fictional database', function () {
    $originalStorage = app()->storagePath();
    $originalEnvironmentPath = app()->environmentPath();
    $directory = storage_path('framework/testing/surveillance-demo-'.bin2hex(random_bytes(4)));
    File::ensureDirectoryExists($directory.'/app/private');
    File::put($directory.'/.env', "APP_NAME=Unchanged\nDB_DATABASE=ahc_surveillance_demo_test\nSURVEILLANCE_DEMO=true\nTEXTBEE_ENABLED=false\n");
    File::put($directory.'/app/private/surveillance-demo.json', json_encode([
        'active' => true, 'database' => 'ahc_surveillance_demo_test',
        'previous_environment' => ['DB_DATABASE' => 'DB_DATABASE=original_clinical_database', 'SURVEILLANCE_DEMO' => null, 'TEXTBEE_ENABLED' => 'TEXTBEE_ENABLED=true'],
    ], JSON_THROW_ON_ERROR));
    try {
        app()->useStoragePath($directory)->useEnvironmentPath($directory);
        $this->artisan('surveillance:demo --restore')->assertSuccessful();
        expect(File::get($directory.'/.env'))->toContain('DB_DATABASE=original_clinical_database')->toContain('TEXTBEE_ENABLED=true')->toContain('APP_NAME=Unchanged')->not->toContain('SURVEILLANCE_DEMO');
        expect(json_decode(File::get($directory.'/app/private/surveillance-demo.json'), true)['active'])->toBeFalse();
    } finally {
        app()->useStoragePath($originalStorage)->useEnvironmentPath($originalEnvironmentPath);
        File::deleteDirectory($directory);
    }
});
