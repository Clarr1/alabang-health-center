<?php

use App\Models\PatientInformation;
use App\Models\Queue;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;

it('uses the shared sidebar on authenticated application pages with one active link', function (string $routeName, string $activeLabel) {
    $user = User::factory()->create(['role' => 'Admin']);
    $response = $this->actingAs($user)->get(route($routeName))->assertOk()
        ->assertSee('id="app-sidebar"', false)->assertSee('id="main-content"', false)
        ->assertSee('Alabang Health Center')->assertSee('aria-label="Main navigation"', false);
    preg_match('/<aside[^>]*id="app-sidebar".*?<\/aside>/s', $response->getContent(), $sidebar);
    expect(substr_count($response->getContent(), 'id="app-sidebar"'))->toBe(1)
        ->and(substr_count($sidebar[0], 'aria-current="page"'))->toBe(1);
    preg_match('/<a[^>]*aria-label="'.preg_quote($activeLabel, '/').'"[^>]*>/', $sidebar[0], $activeLink);
    expect($activeLink[0])->toContain('aria-current="page"');
    if (filter_var(getenv('AHC_SIDEBAR_BROWSER_FIXTURE'), FILTER_VALIDATE_BOOLEAN) && $routeName === 'patient.information-form') {
        File::put(storage_path('app/private/sidebar-browser-fixture.html'), $response->getContent());
    }
})->with([
    'dashboard' => ['dashboard', 'Dashboard'],
    'queue' => ['queues.index', 'Queue Management'],
    'patients' => ['patients.index', 'Patient Record'],
    'registration' => ['patient.information-form', 'Patient Registration'],
    'vital signs' => ['patient.vital-signs-form', 'Vital Signs'],
    'records' => ['medical-records.index', 'Medical Records'],
    'form' => ['doctors-consultation.index', 'Doctor&#039;s Consultation'],
    'follow-ups' => ['follow-ups.index', 'Follow-ups'],
    'diagnoses' => ['diagnoses.index', 'Diagnosis Management'],
    'accounts' => ['users-roles.index', 'Users &amp; Roles'],
    'profile' => ['profile.edit', 'User profile'],
]);

it('retains role based navigation and uses the same links for desktop and mobile', function (string $role, bool $canConsult) {
    $user = User::factory()->create(['role' => $role]);
    $response = $this->actingAs($user)->get(route('dashboard'))->assertOk()
        ->assertSee('aria-label="Dashboard"', false)->assertSee('wire:navigate', false);
    foreach (['Queue Management', 'Patient Record', 'Patient Management'] as $label) {
        if ($role === 'Doctor') {
            $response->assertDontSee('aria-label="'.$label.'"', false);
        } else {
            $response->assertSee('aria-label="'.$label.'"', false);
        }
    }
    foreach (['Medical', 'Medical Records'] as $label) {
        if ($canConsult) {
            $response->assertSee('aria-label="'.$label.'"', false);
        } else {
            $response->assertDontSee('aria-label="'.$label.'"', false);
        }
    }
    if ($canConsult) {
        $response->assertSee('aria-label="Doctor&#039;s Consultation"', false);
    } else {
        $response->assertDontSee('aria-label="Doctor&#039;s Consultation"', false);
        $this->get(route('doctors-consultation.index'))->assertForbidden();
    }
    if ($role === 'Doctor') {
        $response->assertDontSee('aria-label="Vital Signs"', false);
        $response->assertDontSee('aria-label="Patient Registration"', false);
        $this->get(route('patient.information-form'))->assertForbidden();
        $this->get(route('patient.vital-signs-form'))->assertForbidden();
    } else {
        $response->assertSee('aria-label="Vital Signs"', false);
        $response->assertSee('aria-label="Patient Registration"', false);
        $this->get(route('patient.vital-signs-form'))->assertOk();
    }
    if ($canConsult) {
        $response->assertSee('aria-label="Diagnosis Management"', false);
    } else {
        $response->assertDontSee('aria-label="Diagnosis Management"', false);
    }
    if ($role === 'Admin') {
        $response->assertSee('aria-label="Users &amp; Roles"', false);
    } else {
        $response->assertDontSee('aria-label="Users &amp; Roles"', false);
    }
    expect(substr_count($response->getContent(), 'aria-label="Main navigation"'))->toBe(1);
})->with([
    'admin' => ['Admin', true], 'doctor' => ['Doctor', true], 'staff' => ['Local Health Unit Staff', false],
]);

it('includes accessible desktop collapse and mobile drawer controls and offsets content', function () {
    $this->actingAs(User::factory()->create(['role' => 'Admin']))
        ->get(route('medical-records.index'))->assertOk()
        ->assertSee('x-data="sidebarNavigation"', false)
        ->assertSee('data-sidebar-collapse', false)
        ->assertSee('aria-label="Open navigation menu"', false)
        ->assertSee('aria-label="Close navigation menu"', false)
        ->assertSee('data-sidebar-overlay', false)
        ->assertSee('x-trap.inert.noscroll="mobileOpen && !isDesktop"', false)
        ->assertSee('lg:ml-[var(--sidebar-width)]', false)
        ->assertSee('x-on:livewire:navigating.document', false);
});

it('expands the active group and keeps standalone routes outside the dropdowns', function (string $routeName, ?string $activeGroup) {
    $response = $this->actingAs(User::factory()->create(['role' => 'Admin']))->get(route($routeName))->assertOk();
    foreach (['patient-management', 'medical'] as $group) {
        preg_match('/<div[^>]*data-sidebar-group="'.$group.'"[^>]*>/s', $response->getContent(), $container);
        preg_match('/<button[^>]*id="'.$group.'-toggle"[^>]*>/s', $response->getContent(), $toggle);
        expect($container[0])->toContain('data-active="'.($group === $activeGroup ? 'true' : 'false').'"');
        expect($toggle[0])->toContain('aria-expanded="'.($group === $activeGroup ? 'true' : 'false').'"')
            ->toContain('aria-controls="'.$group.'-children"');
        if ($group === $activeGroup) {
            expect($toggle[0])->toContain('font-semibold')->not->toContain('bg-brand-blue')->not->toContain('bg-brand-pink');
        }
    }
})->with([
    'record' => ['patients.index', 'patient-management'],
    'registration' => ['patient.information-form', 'patient-management'],
    'medical records' => ['medical-records.index', 'medical'],
    'consultation' => ['doctors-consultation.index', 'medical'],
    'follow-ups' => ['follow-ups.index', 'medical'],
    'diagnoses' => ['diagnoses.index', 'medical'],
    'dashboard' => ['dashboard', null],
    'queue' => ['queues.index', null],
    'vital signs' => ['patient.vital-signs-form', 'patient-management'],
    'users' => ['users-roles.index', null],
]);

it('provides accessible disclosure buttons and Livewire navigation hooks for both groups', function () {
    $this->actingAs(User::factory()->create(['role' => 'Admin']))->get(route('patients.index'))->assertOk()
        ->assertSee('x-data="sidebarGroup"', false)
        ->assertSee('x-on:livewire:navigated.document="syncActiveGroup()"', false)
        ->assertSee('x-on:keydown.arrow-right.prevent="expandGroup(true)"', false)
        ->assertSee('x-on:keydown.arrow-left.prevent.stop="closeGroup()"', false)
        ->assertSee('x-bind:aria-expanded="open && (!collapsed || !isDesktop)"', false)
        ->assertSee('role="group" aria-labelledby="patient-management-toggle"', false)
        ->assertSee('role="group" aria-labelledby="medical-toggle"', false);
});

it('retains the requested child order and existing standalone navigation positions', function () {
    $response = $this->actingAs(User::factory()->create(['role' => 'Admin']))->get(route('dashboard'))->assertOk();
    preg_match('/<nav[^>]*aria-label="Main navigation".*?<\/nav>/s', $response->getContent(), $navigation);
    $previousPosition = -1;
    foreach (['Dashboard', 'Patient Management', 'Patient Record', 'Patient Registration',
        'Vital Signs', 'Medical', 'Medical Records', 'Doctor&#039;s Consultation', 'Follow-ups', 'Diagnosis Management', 'Queue Management', 'Users &amp; Roles'] as $label) {
        $position = strpos($navigation[0], 'aria-label="'.$label.'"');
        expect($position)->not->toBeFalse()->toBeGreaterThan($previousPosition);
        $previousPosition = $position;
    }
});

it('hides the medical group when all medical navigation abilities are denied', function () {
    $this->actingAs(User::factory()->create(['role' => 'Admin']));
    foreach (['medical-records-view', 'queue-consult', 'follow-ups-manage', 'diagnoses-manage'] as $ability) {
        Gate::define($ability, fn (User $user): bool => false);
    }
    $this->get(route('dashboard'))->assertOk()->assertDontSee('data-sidebar-group="medical"', false)
        ->assertSee('data-sidebar-group="patient-management"', false);
});

it('keeps the patient record link active when editing an existing patient', function () {
    $patient = PatientInformation::factory()->create();
    $response = $this->actingAs(User::factory()->create())->get(route('patients.edit', $patient))->assertOk();
    preg_match('/<a[^>]*aria-label="Patient Record"[^>]*>/', $response->getContent(), $link);
    expect($link[0])->toContain('aria-current="page"');
    $response->assertSee('data-sidebar-group="patient-management" data-active="true"', false);
});

it('keeps account access and csrf protected logout at the bottom of the sidebar', function () {
    $user = User::factory()->create(['name' => "Vidal O'Kon"]);
    $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
    preg_match('/<aside[^>]*id="app-sidebar".*?<\/aside>/s', $response->getContent(), $sidebar);
    expect($sidebar[0])->toContain(e($user->name))->toContain(e($user->email))
        ->toContain('href="'.route('profile.edit').'"')
        ->toContain('method="POST" action="'.route('logout').'"')
        ->toContain('name="_token"')->toContain('aria-label="Log Out"');
    $this->post(route('logout'))->assertRedirect('/');
    $this->assertGuest();
});

it('keeps the fullscreen queue display free from application navigation and content offsets', function () {
    $this->actingAs(User::factory()->create())->get(route('queues.display'))->assertOk()
        ->assertSee('Fullscreen')->assertSee('wire:poll.2s.keep-alive', false)
        ->assertDontSee('id="app-sidebar"', false)->assertDontSee('sidebarNavigation')
        ->assertDontSee('--sidebar-width')->assertDontSee('aria-label="Open navigation menu"', false);
});

it('highlights only vital signs when recording a queue assessment', function () {
    $queue = Queue::factory()->create(['status' => 'Vital Signs']);
    $response = $this->actingAs(User::factory()->create())->get(route('queues.vital-signs', [
        'queue_id' => $queue->queue_id,
        'patient_id' => $queue->patient_id,
    ]))->assertOk()->assertSee('Vital signs assessment')
        ->assertSee('data-sidebar-group="patient-management" data-active="true"', false);

    preg_match('/<aside[^>]*id="app-sidebar".*?<\/aside>/s', $response->getContent(), $sidebar);
    preg_match('/<a[^>]*aria-label="Vital Signs"[^>]*>/', $sidebar[0], $activeLink);
    expect(substr_count($sidebar[0], 'aria-current="page"'))->toBe(1)
        ->and($activeLink[0])->toContain('aria-current="page"');
});
