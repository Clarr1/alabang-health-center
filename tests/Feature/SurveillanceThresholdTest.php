<?php

use App\Livewire\DetectionSettings;
use App\Models\SurveillanceAudit;
use App\Models\SurveillanceSetting;
use App\Models\SyndromeCategory;
use App\Models\User;
use App\Services\SurveillanceAdministration;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

it('saves a category minimum and monitoring status with an attributed audit while preserving statistical settings', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-18 04:00:00', 'UTC'));
    $admin = User::factory()->create(['role' => 'Admin']);
    $this->actingAs($admin);
    $first = SyndromeCategory::factory()->create(['is_active' => true]);
    $second = SyndromeCategory::factory()->create(['is_active' => true]);
    $original = array_replace(SurveillanceSetting::current(), ['alpha' => 0.02, 'watch_alpha' => 0.08, 'baseline_weeks' => 16]);
    SurveillanceSetting::findOrFail(1)->update(['values' => $original]);

    Livewire::test(DetectionSettings::class)->set('categoryId', (string) $first->id)
        ->set('minimumCases', '20')->set('isActive', false)->call('save')->assertHasNoErrors()
        ->set('categoryId', (string) $second->id)->assertSet('minimumCases', '10')->assertSet('isActive', true)
        ->set('minimumCases', '15')->call('save')->assertHasNoErrors()
        ->set('categoryId', (string) $first->id)->assertSet('minimumCases', '20')->assertSet('isActive', false);

    $saved = SurveillanceSetting::current();
    expect($saved['category_thresholds'])->toBe([$first->id => 20, $second->id => 15])
        ->and($saved['alpha'])->toBe(0.02)->and($saved['watch_alpha'])->toBe(0.08)
        ->and($saved['baseline_weeks'])->toBe(16)->and($saved['window_hours'])->toBe(48)
        ->and($saved['settings_reviewed'])->toBeTrue();
    $this->assertDatabaseHas('syndrome_categories', ['id' => $first->id, 'is_active' => false]);
    $this->assertDatabaseHas('surveillance_settings', ['id' => 1, 'updated_by' => $admin->getKey()]);
    $audit = SurveillanceAudit::where('action', 'Category monitoring threshold changed')->oldest('id')->firstOrFail();
    expect($audit->actor_id)->toBe($admin->getKey())->and($audit->created_at->equalTo(now()))->toBeTrue()
        ->and($audit->details['before'])->toBe(['minimum_cases' => 10, 'is_active' => true])
        ->and($audit->details['after'])->toBe(['minimum_cases' => 20, 'is_active' => false]);

    app(SurveillanceAdministration::class)->saveSensitivity('balanced', true);
    expect(SurveillanceSetting::current()['category_thresholds'])->toBe([$first->id => 20, $second->id => 15]);
});

it('rejects invalid minimum cases without saving settings or an audit', function (string $minimumCases) {
    $this->actingAs(User::factory()->create(['role' => 'Admin']));
    $category = SyndromeCategory::factory()->create(['is_active' => true]);
    $original = SurveillanceSetting::current();

    Livewire::test(DetectionSettings::class)->set('categoryId', (string) $category->id)
        ->set('minimumCases', $minimumCases)->set('isActive', false)->call('save')->assertHasErrors('minimum_cases');

    expect(SurveillanceSetting::current())->toBe($original)->and(SurveillanceAudit::count())->toBe(0);
    $this->assertDatabaseHas('syndrome_categories', ['id' => $category->id, 'is_active' => true]);
})->with(['zero' => '0', 'negative' => '-1', 'too large' => '10001', 'fraction' => '1.5', 'missing' => '', 'text' => 'abc']);

it('accepts the supported minimum case boundaries', function (string $minimumCases) {
    $this->actingAs(User::factory()->create(['role' => 'Admin']));
    $category = SyndromeCategory::factory()->create();

    Livewire::test(DetectionSettings::class)->set('categoryId', (string) $category->id)
        ->set('minimumCases', $minimumCases)->call('save')->assertHasNoErrors();

    expect(SurveillanceSetting::current()['category_thresholds'][$category->id])->toBe((int) $minimumCases);
})->with(['lower bound' => '1', 'upper bound' => '10000']);

it('rejects an unknown category and handles an empty category list', function () {
    $this->actingAs(User::factory()->create(['role' => 'Admin']));

    Livewire::test(DetectionSettings::class)->assertSee('No categories yet')->call('save')
        ->assertHasErrors(['category_id', 'minimum_cases'])->call('testDetection')->assertHasErrors('categoryId')
        ->set('categoryId', '999999')->set('minimumCases', '20')->call('save')->assertHasErrors('category_id');

    expect(SurveillanceAudit::count())->toBe(0)->and(SurveillanceSetting::current()['category_thresholds'])->toBe([]);
});

it('forbids unauthorized category threshold changes at the service boundary', function (string $role, bool $active) {
    $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => $active]));
    $category = SyndromeCategory::factory()->create();

    expect(fn () => app(SurveillanceAdministration::class)->saveThreshold([
        'category_id' => $category->id, 'minimum_cases' => 20, 'is_active' => false,
    ]))->toThrow(AuthorizationException::class);

    expect(SurveillanceAudit::count())->toBe(0)->and(SurveillanceSetting::current()['category_thresholds'])->toBe([]);
})->with(['doctor' => ['Doctor', true], 'staff' => ['Local Health Unit Staff', true], 'inactive admin' => ['Admin', false]]);

it('requires authentication for threshold settings', function () {
    $this->get(route('surveillance.settings'))->assertRedirect(route('login'));
});

it('previews the saved category threshold rather than unsaved form values without enabling monitoring', function () {
    $this->actingAs(User::factory()->create(['role' => 'Admin']));
    $category = SyndromeCategory::factory()->create(['is_active' => false]);
    app(SurveillanceAdministration::class)->saveThreshold(['category_id' => $category->id, 'minimum_cases' => 20, 'is_active' => false]);

    Livewire::test(DetectionSettings::class)->set('categoryId', (string) $category->id)->set('minimumCases', '2')
        ->call('testDetection')->assertHasNoErrors()->assertSet('simulation.minimum_cases', 20)
        ->assertSet('simulation.is_active', false)->assertSet('simulation.results.0.observed', 19)
        ->assertSet('simulation.results.1.observed', 20)->assertSet('simulation.results.2.observed', 21)
        ->assertSet('simulation.results.3.outcome', 'Normal');

    $this->assertDatabaseHas('syndrome_categories', ['id' => $category->id, 'is_active' => false]);
    $this->assertDatabaseCount('surveillance_runs', 0);
    $this->assertDatabaseCount('surveillance_alerts', 0);
});
