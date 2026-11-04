<?php

use App\Jobs\RefreshFollowUpDelivery;
use App\Jobs\SendFollowUpSms;
use App\Livewire\FollowUps;
use App\Models\FollowUp;
use App\Models\FollowUpMessage;
use App\Models\PatientInformation;
use App\Models\User;
use App\Services\FollowUpWorkflow;
use App\Services\TextBee;
use Database\Factories\MedicalRecordFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(now('Asia/Manila')->setDate(2026, 11, 2)->setTime(9, 0));
    Http::preventStrayRequests();
    config(['textbee.enabled' => false, 'textbee.api_key' => 'test-placeholder', 'textbee.device_id' => 'test-device']);
    Queue::fake();
    $this->actingAs(User::factory()->create(['role' => 'Local Health Unit Staff']));
    $this->patient = PatientInformation::factory()->create();
    $this->data = [
        'patient_id' => $this->patient->patient_id, 'medical_record_id' => null,
        'date' => '2026-11-05', 'time' => '09:00', 'purpose' => 'Routine checkup',
        'internal_notes' => 'Confidential staff notes', 'mobile' => '0917 123 4567', 'sms_consent' => true,
    ];
});

function sendFollowUpMessage(FollowUpMessage $message): void
{
    (new SendFollowUpSms($message->id))->handle(app(TextBee::class));
}

function fakeAcceptedFollowUpSms(): void
{
    config(['textbee.enabled' => true]);
    Http::fake([
        '*/gateway/devices/test-device' => Http::response(['data' => ['enabled' => true]]),
        '*/gateway/send-sms' => Http::response(['data' => ['success' => true, 'smsBatchId' => 'test-batch']]),
    ]);
}

it('saves a Manila appointment with consent, normalized contact, history and a 24 hour reminder', function () {
    Livewire::test(FollowUps::class)->set('patient_id', (string) $this->patient->patient_id)
        ->set('date', '2026-11-05')->set('time', '09:00')->set('purpose', 'Routine checkup')
        ->set('mobile', '0917 123 4567')->set('sms_consent', true)->call('save')->assertHasNoErrors()->assertSee('Notification history');
    $appointment = FollowUp::firstOrFail();
    expect($appointment->scheduled_at->format('Y-m-d H:i'))->toBe('2026-11-05 01:00')
        ->and($appointment->mobile)->toBe('+639171234567')
        ->and($appointment->events()->count())->toBe(1)
        ->and($appointment->messages()->where('type', 'Reminder')->first()->due_at->format('Y-m-d H:i'))->toBe('2026-11-04 01:00');
    expect($this->patient->fresh()->sms_consent)->toBeTrue();
    Http::assertNothingSent();
});

it('reuses a consultation date and opens the same appointment from the consultation link', function () {
    $record = MedicalRecordFactory::new()->create();
    $this->get(route('follow-ups.index', ['consultation' => $record->medical_record_id]))->assertOk()->assertSee('2026-11-05');
    Livewire::test(FollowUps::class)->set('patient_id', (string) $record->patient_id)->assertSet('date', '2026-11-05')->assertSet('medical_record_id', (string) $record->medical_record_id);
    $appointment = app(FollowUpWorkflow::class)->save([...$this->data, 'patient_id' => $record->patient_id, 'medical_record_id' => (string) $record->medical_record_id]);
    $this->get(route('follow-ups.index', ['consultation' => $record->medical_record_id]))->assertOk()->assertSee('Follow-up #'.$appointment->id);
});

it('rejects invalid numbers, past appointments, duplicate slots and mismatched consultations', function () {
    foreach ([['mobile' => '123'], ['date' => '2026-11-01'], ['time' => '25:00'], ['purpose' => '']] as $invalid) {
        expect(fn () => app(FollowUpWorkflow::class)->save([...$this->data, ...$invalid]))->toThrow(ValidationException::class);
    }
    $record = MedicalRecordFactory::new()->create();
    expect(fn () => app(FollowUpWorkflow::class)->save([...$this->data, 'medical_record_id' => $record->medical_record_id]))->toThrow(ValidationException::class);
    app(FollowUpWorkflow::class)->save($this->data);
    expect(fn () => app(FollowUpWorkflow::class)->save($this->data))->toThrow(ValidationException::class);
    expect(FollowUp::count())->toBe(1);
});

it('keeps reschedule history, replaces pending notices and rejects stale edits', function () {
    $workflow = app(FollowUpWorkflow::class);
    $appointment = $workflow->save($this->data);
    $oldIds = $appointment->messages()->pluck('id');
    $updated = $workflow->save([...$this->data, 'date' => '2026-11-06'], $appointment->id, 1);
    expect($updated->version)->toBe(2)->and($updated->events()->count())->toBe(2)
        ->and(FollowUpMessage::whereIn('id', $oldIds)->where('status', 'Cancelled')->count())->toBe(2);
    expect(fn () => $workflow->save($this->data, $appointment->id, 1))->toThrow(ValidationException::class);
    fakeAcceptedFollowUpSms();
    foreach ($oldIds as $id) {
        sendFollowUpMessage(FollowUpMessage::findOrFail($id));
    }
    Http::assertNothingSent();
    expect($updated->messages()->where('type', 'Reschedule')->where('status', 'Pending')->count())->toBe(1);
});

it('cancels pending sends and preserves terminal appointment history', function (string $status) {
    $workflow = app(FollowUpWorkflow::class);
    $appointment = $workflow->save($this->data);
    $workflow->transition($appointment->id, 1, $status);
    expect($appointment->fresh()->status)->toBe($status)->and($appointment->fresh()->active_slot)->toBeNull()
        ->and($appointment->messages()->where('version', 1)->where('status', 'Pending')->count())->toBe(0)
        ->and($appointment->messages()->where('type', 'Cancellation')->count())->toBe($status === 'Cancelled' ? 1 : 0);
    expect(fn () => $workflow->transition($appointment->id, 1, 'Completed'))->toThrow(ValidationException::class);
})->with(['Cancelled', 'Completed', 'No Show']);

it('honors opted out patients and checks revoked consent and changed contact at send time', function () {
    $workflow = app(FollowUpWorkflow::class);
    $optedOut = $workflow->save([...$this->data, 'sms_consent' => false]);
    expect($optedOut->messages()->count())->toBe(0);
    expect(fn () => $workflow->remind($optedOut->id, 1))->toThrow(ValidationException::class);
    $appointment = $workflow->save([...$this->data, 'date' => '2026-11-06']);
    $this->patient->forceFill(['sms_consent' => false])->save();
    fakeAcceptedFollowUpSms();
    sendFollowUpMessage($appointment->messages()->where('type', 'Confirmation')->first());
    Http::assertNothingSent();
    expect($appointment->messages()->where('type', 'Confirmation')->first()->status)->toBe('Cancelled');
    $this->patient->forceFill(['sms_consent' => true, 'contact_number' => '09181234567'])->save();
    $this->travelTo(now()->addDays(3));
    sendFollowUpMessage($appointment->messages()->where('type', 'Reminder')->first());
    Http::assertNothingSent();
});

it('does not create an immediate automatic reminder inside or exactly at 24 hours', function (string $date, string $time) {
    $appointment = app(FollowUpWorkflow::class)->save([...$this->data, 'date' => $date, 'time' => $time]);
    expect($appointment->messages()->pluck('type')->all())->toBe(['Confirmation']);
})->with([['2026-11-02', '16:00'], ['2026-11-03', '09:00']]);

it('sends a due reminder once across repeated scheduler runs and job retries', function () {
    $appointment = app(FollowUpWorkflow::class)->save($this->data);
    $reminder = $appointment->messages()->where('type', 'Reminder')->first();
    fakeAcceptedFollowUpSms();
    sendFollowUpMessage($reminder);
    Http::assertNothingSent();
    $appointment->messages()->where('type', 'Confirmation')->update(['status' => 'Submitted']);
    $this->travelTo($reminder->due_at);
    $this->artisan('follow-ups:dispatch')->assertSuccessful();
    $this->artisan('follow-ups:dispatch')->assertSuccessful();
    Queue::assertPushed(SendFollowUpSms::class, 2);
    sendFollowUpMessage($reminder);
    sendFollowUpMessage($reminder);
    Http::assertSentCount(2);
    expect($reminder->fresh()->status)->toBe('Submitted')->and($reminder->fresh()->attempts)->toBe(1);
});

it('deduplicates manual and automatic reminders and rejects repeated clicks', function () {
    $workflow = app(FollowUpWorkflow::class);
    $appointment = $workflow->save($this->data);
    $workflow->remind($appointment->id, 1);
    expect(fn () => $workflow->remind($appointment->id, 1))->toThrow(ValidationException::class);
    expect($appointment->messages()->where('type', 'Reminder')->count())->toBe(1);
});

it('treats API acceptance as submitted and only verified carrier reports as delivered', function () {
    $appointment = app(FollowUpWorkflow::class)->save($this->data);
    $confirmation = $appointment->messages()->where('type', 'Confirmation')->first();
    fakeAcceptedFollowUpSms();
    sendFollowUpMessage($confirmation);
    expect($confirmation->fresh()->status)->toBe('Submitted');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/gateway/send-sms') && $request['recipients'] === ['+639171234567'] && ! str_contains($request['message'], 'Confidential') && ! str_contains($request['message'], 'Routine'));
    Http::fake(['*/sms-batch/*' => Http::response(['data' => ['messages' => [['recipient' => $confirmation->recipient, 'status' => 'delivered']]]])]);
    (new RefreshFollowUpDelivery($confirmation->id))->handle(app(TextBee::class));
    expect($confirmation->fresh()->status)->toBe('Delivered');
});

it('records rejected and ambiguous sends without uncontrolled retries', function (int $httpStatus, string $expected) {
    $appointment = app(FollowUpWorkflow::class)->save($this->data);
    $message = $appointment->messages()->where('type', 'Confirmation')->first();
    config(['textbee.enabled' => true]);
    Http::fake(['*/gateway/devices/test-device' => Http::response(['data' => ['enabled' => true]]), '*/gateway/send-sms' => Http::response([], $httpStatus)]);
    sendFollowUpMessage($message);
    sendFollowUpMessage($message);
    expect($message->fresh()->status)->toBe($expected)->and($message->fresh()->attempts)->toBe(1);
    Http::assertSentCount(2);
})->with([[400, 'Failed'], [401, 'Failed'], [500, 'Uncertain'], [200, 'Uncertain']]);

it('defers and bounds rate limit retries and retains every attempt', function () {
    $appointment = app(FollowUpWorkflow::class)->save($this->data);
    $message = $appointment->messages()->where('type', 'Confirmation')->first();
    config(['textbee.enabled' => true]);
    Http::fake(['*/gateway/devices/test-device' => Http::response(['data' => ['enabled' => true]]), '*/gateway/send-sms' => Http::response([], 429)]);
    sendFollowUpMessage($message);
    expect($message->fresh()->status)->toBe('Pending');
    $this->travel(5)->minutes();
    sendFollowUpMessage($message);
    expect($message->fresh()->status)->toBe('Failed')->and($message->fresh()->attempt_history)->toHaveCount(2);
    sendFollowUpMessage($message);
    Http::assertSentCount(4);
});

it('does not send when device is disabled, integration is off or a request times out', function () {
    $appointment = app(FollowUpWorkflow::class)->save($this->data);
    $message = $appointment->messages()->where('type', 'Confirmation')->first();
    sendFollowUpMessage($message);
    Http::assertNothingSent();
    config(['textbee.enabled' => true]);
    Http::fake(['*/gateway/devices/test-device' => Http::response(['data' => ['enabled' => false]])]);
    sendFollowUpMessage($message);
    expect($message->fresh()->status)->toBe('Failed');
    app(FollowUpWorkflow::class)->retry($message->id);
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*/gateway/devices/test-device' => Http::response(['data' => ['enabled' => true]]), '*/gateway/send-sms' => Http::failedConnection()]);
    sendFollowUpMessage($message);
    expect($message->fresh()->status)->toBe('Uncertain');
    expect(fn () => app(FollowUpWorkflow::class)->retry($message->id))->toThrow(ValidationException::class);
});

it('shows delivery failure and retains submitted status if delivery cannot be checked', function () {
    $appointment = app(FollowUpWorkflow::class)->save($this->data);
    $message = $appointment->messages()->where('type', 'Confirmation')->first();
    fakeAcceptedFollowUpSms();
    sendFollowUpMessage($message);
    Http::fake(['*/sms-batch/*' => Http::failedConnection()]);
    (new RefreshFollowUpDelivery($message->id))->handle(app(TextBee::class));
    expect($message->fresh()->status)->toBe('Submitted');
    $this->travel(5)->minutes();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['*/sms-batch/*' => Http::response(['data' => ['messages' => [['recipient' => $message->recipient, 'status' => 'failed']]]])]);
    (new RefreshFollowUpDelivery($message->id))->handle(app(TextBee::class));
    expect($message->fresh()->status)->toBe('Failed');
    expect(fn () => app(FollowUpWorkflow::class)->retry($message->id))->toThrow(ValidationException::class);
});

it('protects the route and all mutation actions from unauthorized roles', function () {
    $appointment = app(FollowUpWorkflow::class)->save($this->data);
    $component = Livewire::test(FollowUps::class);
    DB::statement('PRAGMA ignore_check_constraints = ON');
    auth()->user()->forceFill(['role' => 'Unsupported'])->save();
    DB::statement('PRAGMA ignore_check_constraints = OFF');
    $this->get(route('follow-ups.index'))->assertForbidden();
    $component->call('remind', $appointment->id, 1)->assertForbidden();
    expect(fn () => app(FollowUpWorkflow::class)->save($this->data))->toThrow(AuthorizationException::class);
    expect(fn () => app(FollowUpWorkflow::class)->transition($appointment->id, 1, 'Cancelled'))->toThrow(AuthorizationException::class);
    expect(fn () => app(FollowUpWorkflow::class)->retry($appointment->messages()->first()->id))->toThrow(AuthorizationException::class);
});

it('lets active clinical roles use the page and blocks inactive accounts', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]));
    $this->get(route('follow-ups.index'))->assertOk()->assertSee('Follow-up schedule')->assertSee('No follow-ups match');
    auth()->user()->forceFill(['is_active' => false])->save();
    $this->get(route('follow-ups.index'))->assertForbidden();
})->with(['Admin', 'Doctor', 'Local Health Unit Staff']);

it('filters by patient status and Manila calendar dates', function () {
    $appointment = app(FollowUpWorkflow::class)->save([...$this->data, 'time' => '00:30']);
    Livewire::test(FollowUps::class)->set('from', '2026-11-05')->set('to', '2026-11-05')->assertSee('Routine checkup')
        ->set('status', 'Completed')->assertDontSee('Routine checkup')->call('clearFilters')->set('search', (string) $appointment->patient_id)->assertSee('Routine checkup')
        ->set('search', 'no matching patient')->assertDontSee('Routine checkup');
});
it('does not duplicate notices for unchanged saves or internal edits', function () {
    $workflow = app(FollowUpWorkflow::class);
    $appointment = $workflow->save($this->data);
    $workflow->save($this->data, $appointment->id, 1);
    expect($appointment->fresh()->version)->toBe(1)->and($appointment->messages()->count())->toBe(2);
    $updated = $workflow->save([...$this->data, 'internal_notes' => 'Updated private note'], $appointment->id, 1);
    expect($updated->version)->toBe(2)->and($updated->notification_version)->toBe(1)->and($updated->messages()->count())->toBe(2);
    fakeAcceptedFollowUpSms();
    sendFollowUpMessage($updated->messages()->where('type', 'Confirmation')->first());
    expect($updated->messages()->where('type', 'Confirmation')->first()->status)->toBe('Submitted');
    $workflow->remind($updated->id, 2);
    expect(fn () => $workflow->remind($updated->id, 2))->toThrow(ValidationException::class);
});

it('sends only the replacement notice after rescheduling inside the reminder window', function () {
    $workflow = app(FollowUpWorkflow::class);
    $appointment = $workflow->save($this->data);
    $updated = $workflow->save([...$this->data, 'date' => '2026-11-02', 'time' => '17:00'], $appointment->id, 1);
    fakeAcceptedFollowUpSms();
    foreach ($updated->messages as $message) {
        sendFollowUpMessage($message);
    }
    Http::assertSentCount(2);
    expect($updated->messages()->where('status', 'Submitted')->count())->toBe(1)
        ->and($updated->messages()->where('version', 2)->pluck('type')->all())->toBe(['Reschedule']);
});

it('queues a cancellation notice and prevents obsolete confirmations from sending', function () {
    $workflow = app(FollowUpWorkflow::class);
    $appointment = $workflow->save($this->data);
    $workflow->transition($appointment->id, 1, 'Cancelled');
    fakeAcceptedFollowUpSms();
    foreach ($appointment->messages as $message) {
        sendFollowUpMessage($message);
    }
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/gateway/send-sms') && str_contains($request['message'], 'Cancelled'));
});

it('supports one manual reminder for an appointment created within 24 hours', function () {
    $workflow = app(FollowUpWorkflow::class);
    $appointment = $workflow->save([...$this->data, 'date' => '2026-11-02', 'time' => '17:00']);
    $workflow->remind($appointment->id, 1);
    expect($appointment->messages()->where('type', 'Reminder')->count())->toBe(1);
    expect(fn () => $workflow->remind($appointment->id, 1))->toThrow(ValidationException::class);
});

it('cancels pending messages across the patients appointments on opting out', function () {
    $workflow = app(FollowUpWorkflow::class);
    $first = $workflow->save($this->data);
    $second = $workflow->save([...$this->data, 'date' => '2026-11-06']);
    $workflow->save([...$this->data, 'sms_consent' => false], $first->id, 1);
    expect(FollowUpMessage::where('status', 'Pending')->count())->toBe(0);
    fakeAcceptedFollowUpSms();
    foreach ($second->messages as $message) {
        sendFollowUpMessage($message);
    }
    Http::assertNothingSent();
});

it('preserves pagination and filters without exposing other appointments', function () {
    $workflow = app(FollowUpWorkflow::class);
    for ($day = 5; $day <= 14; $day++) {
        $workflow->save([...$this->data, 'date' => sprintf('2026-11-%02d', $day), 'purpose' => 'Visit '.$day]);
    }
    Livewire::test(FollowUps::class)->assertSee('Visit 5')->assertDontSee('Visit 14')->call('gotoPage', 2)->assertSee('Visit 14')
        ->set('from', '2026-11-14')->assertSee('Visit 14')->assertDontSee('Visit 5');
});

it('never marks mismatched or missing carrier reports as delivered', function () {
    $appointment = app(FollowUpWorkflow::class)->save($this->data);
    $message = $appointment->messages()->where('type', 'Confirmation')->first();
    fakeAcceptedFollowUpSms();
    sendFollowUpMessage($message);
    Http::fake(['*/sms-batch/*' => Http::response(['data' => ['messages' => [['recipient' => '+639181234567', 'status' => 'delivered']]]])]);
    (new RefreshFollowUpDelivery($message->id))->handle(app(TextBee::class));
    expect($message->fresh()->status)->toBe('Submitted');
});

it('keeps notifications pending with clear feedback until configuration is complete', function () {
    app(FollowUpWorkflow::class)->save($this->data);
    config(['textbee.enabled' => true, 'textbee.device_id' => null]);
    Livewire::test(FollowUps::class)->assertSee('configuration is incomplete');
    $this->artisan('follow-ups:dispatch')->assertSuccessful();
    Queue::assertNothingPushed();
    Http::assertNothingSent();
    expect(FollowUpMessage::where('status', 'Pending')->count())->toBe(2);
});
