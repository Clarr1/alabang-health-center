<?php

use App\Livewire\AuditLogs;
use App\Models\AuditLog;
use App\Models\PatientInformation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('allows only active administrators to view the audit log', function (string $role, int $status) {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)->get(route('audit-logs.index'))->assertStatus($status);
    Livewire::actingAs($user)->test(AuditLogs::class)->assertStatus($status);
})->with([
    ['Admin', 200],
    ['Local Health Unit Staff', 403],
    ['Doctor', 403],
]);

it('hides the audit log navigation item from non-administrators', function () {
    $staff = User::factory()->create(['role' => 'Local Health Unit Staff']);

    $this->actingAs($staff)->get(route('dashboard'))->assertDontSee('Audit Log');
});

it('records changed fields and values for patient updates but not unchanged saves', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $patient = PatientInformation::factory()->create(['address' => 'Old address']);
    $this->actingAs($admin);

    $patient->update(['address' => 'New address']);
    expect(AuditLog::count())->toBe(1);
    $audit = AuditLog::firstOrFail();
    expect($audit->action)->toBe('UPDATE')
        ->and($audit->module)->toBe('Patient Management')
        ->and($audit->record_id)->toBe((string) $patient->getKey())
        ->and($audit->changed_fields)->toContain('address')
        ->and($audit->previous_values['address'])->toBe('Old address')
        ->and($audit->new_values['address'])->toBe('New address');

    $patient->save();
    expect(AuditLog::count())->toBe(1);
});

it('records deletes with the previous record values', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $patient = PatientInformation::factory()->create(['first_name' => 'Deleted', 'last_name' => 'Patient']);
    $this->actingAs($admin);

    $patient->delete();

    $audit = AuditLog::sole();
    expect($audit->action)->toBe('DELETE')
        ->and($audit->record_description)->toContain('Deleted Patient')
        ->and($audit->previous_values['first_name'])->toBe('Deleted');
});

it('records role changes and preserves transaction consistency', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $user = User::factory()->create(['role' => 'Doctor']);
    $this->actingAs($admin);

    $user->forceFill(['role' => 'Local Health Unit Staff'])->save();
    expect(AuditLog::sole()->previous_values['role'])->toBe('Doctor')
        ->and(AuditLog::sole()->new_values['role'])->toBe('Local Health Unit Staff')
        ->and(AuditLog::sole()->user_id)->toBe($admin->getKey());

    expect(fn () => DB::transaction(function () use ($user): void {
        $user->forceFill(['name' => 'Rolled Back'])->save();
        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class);
    expect(AuditLog::count())->toBe(1);
});

it('does not expose audit mutations through the application model', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $this->actingAs($admin);
    PatientInformation::factory()->create()->update(['address' => 'Changed']);
    $audit = AuditLog::sole();

    expect(fn () => $audit->update(['module' => 'Forged']))->toThrow(LogicException::class);
    expect(fn () => $audit->delete())->toThrow(LogicException::class);
});

it('supports searching, filtering, details, and pagination', function () {
    $admin = User::factory()->create(['role' => 'Admin']);
    $patient = PatientInformation::factory()->create(['address' => 'Filterable address']);
    $this->actingAs($admin);
    $patient->update(['address' => 'Updated address']);

    Livewire::test(AuditLogs::class)
        ->set('search', 'Filterable')
        ->assertSee('Patient Management')
        ->set('actionFilter', 'DELETE')
        ->assertSee('No audit entries match')
        ->set('actionFilter', 'UPDATE')
        ->call('viewDetails', AuditLog::sole()->id)
        ->assertSet('selectedAuditId', AuditLog::sole()->id)
        ->assertSee('Previous Values');
});
