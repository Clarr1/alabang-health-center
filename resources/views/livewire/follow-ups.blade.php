<div class="relative isolate mx-auto max-w-screen-2xl px-4 py-7 sm:px-7 lg:px-8" wire:poll.30s>
    <img src="{{ asset('images/barangay-alabang.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute right-5 top-6 -z-10 w-[440px] max-w-full opacity-[0.04]">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-semibold tracking-tight">Follow-up checkups &amp; SMS</h1><p class="mt-2 text-xs text-muted">Schedule the next checkup and confirm the patient's contact before sending a reminder.</p></div>
        <button type="button" wire:click="newAppointment" class="patient-button bg-white">+ Schedule follow-up</button>
    </div>
    @if (session('follow-up-status'))<p role="status" class="mb-4 rounded-lg border border-line bg-white p-3 text-sm">{{ session('follow-up-status') }}</p>@endif
    @if (!$smsConfigured)<p role="status" class="mb-4 rounded-lg bg-white p-3 text-xs text-muted">SMS is disabled or configuration is incomplete. Appointments can be saved; notifications stay pending until SMS is configured and enabled.</p>@endif
    @error('appointment')<p role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</p>@enderror
    <p wire:loading.delay role="status" class="mb-3 text-xs text-muted">Updating follow-ups…</p>
    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1.5fr)_minmax(320px,1fr)]">
        <div class="min-w-0 space-y-5">
            <section class="patient-card">
                <h2 class="text-base font-semibold">Follow-up schedule</h2><p class="mt-1 text-xs text-muted">{{ $appointments->total() }} checkups · All times Asia/Manila</p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label class="patient-label" for="follow-search">Search patient name or ID</label><input id="follow-search" wire:model.live.debounce.300ms="search" class="patient-input w-full" placeholder="Search appointments"><x-input-error :messages="$errors->get('search')" /></div>
                    <div><label class="patient-label" for="follow-from">From date</label><input id="follow-from" type="date" wire:model.live="from" class="patient-input w-full"><x-input-error :messages="$errors->get('from')" /></div>
                    <div><label class="patient-label" for="follow-to">To date</label><input id="follow-to" type="date" wire:model.live="to" class="patient-input w-full"><x-input-error :messages="$errors->get('to')" /></div>
                    <div><label class="patient-label" for="follow-status">Status</label><select id="follow-status" wire:model.live="status" class="patient-input w-full"><option value="">All statuses</option>@foreach (['Scheduled', 'Completed', 'Cancelled', 'No Show'] as $option)<option>{{ $option }}</option>@endforeach</select><x-input-error :messages="$errors->get('status')" /></div>
                    <div class="flex items-end"><button type="button" wire:click="clearFilters" class="patient-button">Clear filters</button></div>
                </div>
                <div class="mt-5 overflow-x-auto" wire:loading.class="opacity-50">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-blush text-[11px]"><tr><th class="p-3 font-semibold">Patient / Purpose</th><th class="p-3 font-semibold">Checkup</th><th class="p-3 font-semibold">SMS</th><th class="p-3 font-semibold">Action</th></tr></thead>
                        <tbody class="divide-y divide-line">
                        @forelse ($appointments as $appointment)
                            @php($latestSms = $appointment->messages->first(fn ($message) => $message->type !== 'Reminder' || $message->status !== 'Pending'))
                            <tr wire:key="follow-{{ $appointment->id }}" @class(['bg-blush/60' => $appointmentId === $appointment->id])>
                                <td class="p-3"><p class="font-semibold">{{ $appointment->patient->first_name }} {{ $appointment->patient->middle_name }} {{ $appointment->patient->last_name }}</p><p class="mt-1 text-[11px] text-muted">Patient ID {{ $appointment->patient_id }}</p><p class="mt-1 break-words text-muted">{{ $appointment->purpose }}</p></td>
                                <td class="whitespace-nowrap p-3"><p>{{ $appointment->scheduled_at->setTimezone('Asia/Manila')->format('d M Y') }}</p><p class="mt-1">{{ $appointment->scheduled_at->setTimezone('Asia/Manila')->format('h:i A') }}</p><span class="mt-2 inline-block rounded bg-blush px-2 py-1 text-[10px]">{{ $appointment->status }}</span></td>
                                <td class="p-3"><span @class(['inline-block rounded px-2 py-1 text-[10px] font-semibold', 'bg-red-50 text-red-700' => in_array($latestSms?->status, ['Failed', 'Uncertain']), 'bg-slate-100' => !in_array($latestSms?->status, ['Failed', 'Uncertain'])])>{{ $latestSms?->status ?? 'No SMS' }}</span></td>
                                <td class="p-3"><button type="button" wire:click="edit({{ $appointment->id }})" class="font-medium underline decoration-line underline-offset-4">{{ $appointmentId === $appointment->id ? 'Selected' : 'View / Edit' }}</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-8 text-center text-muted">No follow-ups match these filters. Schedule a follow-up or clear your filters.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-5">{{ $appointments->links() }}</div>
            </section>
            <section class="patient-card">
                <h2 class="text-base font-semibold">Delivery needs attention</h2><p class="mt-1 text-xs text-muted">Verify patient contact information and device setup before retrying.</p>
                @forelse ($attention as $message)
                    <article class="mt-4 rounded-lg border border-line bg-blush p-3" wire:key="sms-attention-{{ $message->id }}">
                        <p class="text-xs font-semibold">{{ $message->followUp->patient->first_name }} {{ $message->followUp->patient->last_name }} · {{ $message->recipient }}</p><p class="mt-2 text-xs">{{ $message->status }}: {{ $message->outcome }}</p>
                        <p class="mt-2 text-[11px] text-muted">{{ $message->type }} · Last attempt {{ $message->attempted_at?->setTimezone('Asia/Manila')->format('d M Y, h:i A') ?? 'Not attempted' }}</p>
                        <div class="mt-3 flex flex-wrap gap-2"><button type="button" wire:click="edit({{ $message->follow_up_id }})" class="patient-button bg-brand-blue text-xs">Verify / update contact</button>
                            @if ($message->status === 'Failed' && !$message->batch_id && $message->attempts < 3 && $message->version === $message->followUp->notification_version)
                                <button type="button" wire:click="retry({{ $message->id }})" wire:confirm="Have you verified the contact number and fixed the sending issue? Retry this SMS?" wire:loading.attr="disabled" class="patient-button bg-white text-xs">Retry SMS</button>
                            @endif
                        </div>
                    </article>
                @empty<p class="mt-4 text-xs text-muted">No failed or uncertain messages.</p>@endforelse
            </section>
        </div>
        <div class="min-w-0 space-y-5">
            <section class="patient-card">
                <div class="flex items-center justify-between gap-2"><h2 class="text-base font-semibold">{{ $selected ? 'Follow-up #'.$selected->id : 'Schedule a follow-up' }}</h2>@if($selected)<span class="text-xs text-muted">{{ $selected->status }}</span>@endif</div>
                <p class="mt-1 text-xs text-muted">{{ $medical_record_id ? 'From consultation #'.$medical_record_id : 'Choose an existing patient to schedule their next visit.' }}</p>
                <form wire:submit="save" class="mt-5 space-y-4">
                    <fieldset @disabled($selected && $selected->status !== 'Scheduled') class="space-y-4">
                        @if (!$selected)<div><label class="patient-label" for="patient-search">Find patient</label><input id="patient-search" x-on:keydown.enter.prevent wire:model.live.debounce.300ms="patientSearch" class="patient-input w-full" placeholder="Name or patient ID"><p class="mt-1 text-[11px] text-muted">Showing up to 50 matches. Search to find another patient.</p></div>@endif
                        <div><label class="patient-label" for="follow-patient">Patient *</label><select id="follow-patient" wire:model.live="patient_id" @disabled($selected) class="patient-input w-full" required><option value="">Select patient</option>@foreach ($patients as $patient)<option value="{{ $patient->patient_id }}">{{ $patient->first_name }} {{ $patient->last_name }} · ID {{ $patient->patient_id }}</option>@endforeach</select><x-input-error :messages="$errors->get('patient_id')" /></div>
                        <div><label class="patient-label" for="follow-consultation">Consultation (optional)</label><select id="follow-consultation" wire:model.live="medical_record_id" @disabled($selected) class="patient-input w-full"><option value="">No consultation link</option>@foreach($consultations as $consultation)<option value="{{ $consultation->medical_record_id }}">#{{ $consultation->medical_record_id }} · {{ $consultation->consultation_date->format('d M Y') }}{{ $consultation->follow_up_date ? ' · Follow-up '.$consultation->follow_up_date->format('d M Y') : '' }}</option>@endforeach</select><p class="mt-1 text-[11px] text-muted">The consultation's follow-up date is filled in automatically.</p><x-input-error :messages="$errors->get('medical_record_id')" /></div>
                        <div><label class="patient-label" for="follow-purpose">Purpose *</label><input id="follow-purpose" wire:model="purpose" maxlength="255" required class="patient-input w-full"><x-input-error :messages="$errors->get('purpose')" /></div>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label class="patient-label" for="follow-date">Date *</label><input id="follow-date" wire:model.live="date" type="date" required class="patient-input w-full"><x-input-error :messages="$errors->get('date')" /></div>
                            <div><label class="patient-label" for="follow-time">Time *</label><input id="follow-time" wire:model.live="time" type="time" required class="patient-input w-full"><x-input-error :messages="$errors->get('time')" /></div>
                        </div><p class="text-[11px] text-muted">Appointment date and time are in Asia/Manila.</p>
                        <div><label class="patient-label" for="follow-notes">Internal notes (optional)</label><textarea id="follow-notes" wire:model="internal_notes" rows="3" maxlength="5000" class="patient-input w-full"></textarea><p class="mt-1 text-[11px] text-muted">For staff only. Never included in SMS.</p><x-input-error :messages="$errors->get('internal_notes')" /></div>
                        <div><label class="patient-label" for="follow-mobile">Verified patient mobile *</label><input id="follow-mobile" type="tel" wire:model="mobile" required class="patient-input w-full" placeholder="0917 482 1630"><p class="mt-1 text-[11px] text-muted">Verify with the patient. Saving updates their contact record.</p><x-input-error :messages="$errors->get('mobile')" /></div>
                        <label class="flex items-start gap-2 text-xs"><input type="checkbox" wire:model="sms_consent" class="mt-0.5 rounded border-line text-ink focus:ring-brand-blue"><span>Patient agrees to appointment SMS notifications. Uncheck to opt out for all appointments.</span></label>
                        <div class="rounded-lg bg-blush p-4"><h3 class="text-xs font-semibold">SMS preview</h3><p class="mt-2 text-xs leading-relaxed">{{ $smsPreview }}</p><p class="mt-2 text-[10px] text-muted">No clinical details or internal notes included.</p></div>
                        <button type="submit" wire:loading.attr="disabled" class="patient-button bg-brand-pink">{{ $selected ? 'Save changes & queue notice' : 'Save schedule & queue SMS' }}</button>
                    </fieldset>
                </form>
                @if ($selected && $selected->status === 'Scheduled')
                    <div class="mt-5 flex flex-wrap gap-2 border-t border-line pt-4">
                        <button type="button" wire:click="remind({{ $selected->id }}, {{ $selected->version }})" wire:confirm="Send a reminder now? Only one reminder is allowed for this appointment version." wire:loading.attr="disabled" class="patient-button bg-brand-blue text-xs">Send Reminder</button>
                        @foreach(['Completed', 'No Show', 'Cancelled'] as $targetStatus)<button type="button" wire:click="transition({{ $selected->id }}, {{ $selected->version }}, '{{ $targetStatus }}')" wire:confirm="Mark this appointment {{ $targetStatus }}? Pending messages will be cancelled." wire:loading.attr="disabled" class="patient-button text-xs">{{ $targetStatus === 'Cancelled' ? 'Cancel appointment' : $targetStatus }}</button>@endforeach
                    </div>
                @endif
                <p class="mt-4 text-[11px] leading-relaxed text-muted">Pending messages are replaced when an appointment changes. SMS already accepted by TextBee cannot be recalled; an update or cancellation notice follows.</p>
            </section>
            @if ($selected)
                <section class="patient-card"><h2 class="text-base font-semibold">Notification history</h2>
                    @forelse($selected->messages as $message)
                        <article class="mt-4 border-t border-line pt-3" wire:key="history-sms-{{ $message->id }}"><p class="text-xs font-semibold">{{ $message->type }} · {{ $message->status }} · v{{ $message->version }}</p><p class="mt-1 text-[11px] text-muted">{{ $message->recipient }} · {{ $message->attempted_at?->setTimezone('Asia/Manila')->format('d M Y, h:i A') ?? 'Not attempted' }}</p><p class="mt-2 text-xs">{{ $message->outcome ?? 'Waiting until '.$message->due_at->setTimezone('Asia/Manila')->format('d M Y, h:i A') }}</p>@foreach($message->attempt_history ?? [] as $attempt)<p class="mt-2 text-[11px] text-muted">{{ \Carbon\CarbonImmutable::parse($attempt['at'])->setTimezone('Asia/Manila')->format('d M Y, h:i A') }} · {{ $attempt['status'] }} · {{ $attempt['outcome'] }}</p>@endforeach</article>
                    @empty<p class="mt-3 text-xs text-muted">No notifications. SMS requires the patient's consent.</p>@endforelse
                </section>
                <section class="patient-card"><h2 class="text-base font-semibold">Appointment history</h2>@foreach($selected->events as $event)<article class="mt-4 border-t border-line pt-3" wire:key="event-{{ $event->id }}"><p class="text-xs font-semibold">{{ $event->action }}</p><p class="mt-1 text-[11px] text-muted">{{ $event->created_at->setTimezone('Asia/Manila')->format('d M Y, h:i A') }} · Staff ID {{ $event->actor_id }} · v{{ $event->snapshot['version'] }}</p><p class="mt-1 text-xs">{{ \Carbon\CarbonImmutable::parse($event->snapshot['scheduled_at'])->setTimezone('Asia/Manila')->format('d M Y, h:i A') }} · {{ $event->snapshot['status'] }}</p></article>@endforeach</section>
            @endif
        </div>
    </div>
</div>
