<?php

use App\Models\User;

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
})->with([
    'dashboard' => ['dashboard', 'Dashboard'],
    'queue' => ['queues.index', 'Queue Management'],
    'patients' => ['patients.index', 'Patients'],
    'records' => ['medical-records.index', 'Medical Records'],
    'form' => ['doctors-consultation.index', 'Doctor&#039;s Consultation'],
    'profile' => ['profile.edit', 'User profile'],
]);

it('retains role based navigation and uses the same links for desktop and mobile', function (string $role, bool $canConsult) {
    $user = User::factory()->create(['role' => $role]);
    $response = $this->actingAs($user)->get(route('dashboard'))->assertOk()
        ->assertSee('aria-label="Dashboard"', false)
        ->assertSee('aria-label="Queue Management"', false)
        ->assertSee('aria-label="Patients"', false)
        ->assertSee('aria-label="Medical Records"', false)
        ->assertSee('wire:navigate', false);
    if ($canConsult) {
        $response->assertSee('aria-label="Doctor&#039;s Consultation"', false);
    } else {
        $response->assertDontSee('aria-label="Doctor&#039;s Consultation"', false);
        $this->get(route('doctors-consultation.index'))->assertForbidden();
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

it('keeps account access and csrf protected logout at the bottom of the sidebar', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();
    preg_match('/<aside[^>]*id="app-sidebar".*?<\/aside>/s', $response->getContent(), $sidebar);
    expect($sidebar[0])->toContain($user->name)->toContain($user->email)
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
