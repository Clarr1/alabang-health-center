<?php

use App\Livewire\DetectionSettings;
use App\Models\SurveillanceAudit;
use App\Models\SurveillanceSetting;
use App\Models\User;
use App\Services\SurveillanceAdministration;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('persists reviewed sensitivity thresholds while retaining internal reliability settings', function (string $sensitivity, float $alpha, float $watchAlpha) {
    $this->actingAs(User::factory()->create(['role' => 'Admin']));
    $original = array_replace(SurveillanceSetting::current(), ['baseline_weeks' => 16, 'minimum_coverage' => 0.9]);
    SurveillanceSetting::updateOrCreate(['id' => 1], ['values' => $original]);

    Livewire::test(DetectionSettings::class)->set('sensitivity', $sensitivity)->set('reviewed', true)
        ->call('save')->assertHasNoErrors()->assertSet('reviewed', false);

    $saved = SurveillanceSetting::current();
    expect((float) $saved['alpha'])->toBe($alpha)->and((float) $saved['watch_alpha'])->toBe($watchAlpha)
        ->and($saved['window_hours'])->toBe(48)->and($saved['baseline_weeks'])->toBe(16)
        ->and($saved['minimum_coverage'])->toBe(0.9)->and($saved['settings_reviewed'])->toBeTrue()
        ->and(SurveillanceSetting::sensitivity())->toBe($sensitivity)
        ->and(SurveillanceAudit::sole()->details['before']['baseline_weeks'])->toBe(16);
    Livewire::test(DetectionSettings::class)->assertSet('sensitivity', $sensitivity);
})->with([['low', 0.001, 0.01], ['balanced', 0.01, 0.05], ['high', 0.05, 0.1]]);

it('requires review and rejects unknown sensitivity values without changing settings', function () {
    $this->actingAs(User::factory()->create(['role' => 'Admin']));
    $original = SurveillanceSetting::current();
    Livewire::test(DetectionSettings::class)->call('save')->assertHasErrors('reviewed')
        ->set('reviewed', true)->set('sensitivity', 'invalid')->call('save')->assertHasErrors('sensitivity');
    expect(SurveillanceSetting::current())->toBe($original)->and(SurveillanceAudit::count())->toBe(0);
});

it('retains legacy custom thresholds until an administrator explicitly selects a preset and fixes the monitoring window', function () {
    $this->actingAs(User::factory()->create(['role' => 'Admin']));
    $original = array_replace(SurveillanceSetting::current(), ['alpha' => 0.02, 'watch_alpha' => 0.08, 'window_hours' => 96]);
    SurveillanceSetting::updateOrCreate(['id' => 1], ['values' => $original]);
    Livewire::test(DetectionSettings::class)->assertSet('sensitivity', 'custom')->assertSee('custom thresholds are still in use');
    expect(SurveillanceSetting::find(1)->values)->toBe($original)->and(SurveillanceSetting::current()['window_hours'])->toBe(48);
    expect(fn () => app(SurveillanceAdministration::class)->saveSettings(array_replace($original, ['settings_reviewed' => true])))
        ->toThrow(ValidationException::class);
});

it('runs a visibly simulated statistical preview without any database writes or external notifications', function () {
    $this->actingAs(User::factory()->create(['role' => 'Admin']));
    app(SurveillanceAdministration::class)->saveSensitivity('balanced', true);
    Mail::fake();
    Notification::fake();
    Http::preventStrayRequests();
    Http::fake();
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });
    Livewire::test(DetectionSettings::class)->call('testDetection')->assertHasNoErrors()
        ->assertSet('simulation.cases', 17)->assertSet('simulation.historical_cases', 24)->assertSet('simulation.test_alerts', 1)
        ->assertSet('simulation.results.0.outcome', 'Normal')->assertSet('simulation.results.1.outcome', 'Alert')
        ->assertSet('simulation.results.2.outcome', 'Insufficient Data')->assertSee('SIMULATED RESULTS ONLY')
        ->assertSee('17 simulated current cases evaluated');
    expect(array_filter($queries, fn (string $sql): bool => preg_match('/^\s*(insert|update|delete|replace|create|drop|alter)\b/i', $sql) === 1))->toBeEmpty();
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
    Http::assertNothingSent();
});

it('does not bypass unreviewed settings in the simulated preview', function () {
    $this->actingAs(User::factory()->create(['role' => 'Admin']));
    Livewire::test(DetectionSettings::class)->call('testDetection')
        ->assertSet('simulation.results.0.outcome', 'Insufficient Data')->assertSet('simulation.test_alerts', 0);
});

it('restricts settings and simulated testing to active administrators', function (string $role, bool $active) {
    $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
    $this->get(route('surveillance.settings'))->assertForbidden();
    Livewire::test(DetectionSettings::class)->assertForbidden();
    expect(fn () => app(SurveillanceAdministration::class)->saveSensitivity('high', true))->toThrow(AuthorizationException::class);
})->with([['Doctor', true], ['Local Health Unit Staff', true], ['Admin', false]]);

it('rechecks authorization for settings actions after a role is revoked', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $component = Livewire::actingAs($admin)->test(DetectionSettings::class)->set('reviewed', true);
    $admin->forceFill(['role' => 'Doctor'])->save();
    $component->call('save')->assertForbidden();
    $admin->forceFill(['role' => 'Admin'])->save();
    $component = Livewire::actingAs($admin)->test(DetectionSettings::class);
    $admin->forceFill(['is_active' => false])->save();
    $component->call('testDetection')->assertForbidden();
});
