<div class="relative mx-auto max-w-[1440px] px-4 py-7 text-ink sm:px-7 lg:px-8 lg:py-8">
    <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-2xl font-semibold tracking-tight">Audit Log</h1><p class="mt-2 text-sm text-muted">Review who changed or deleted records. Audit history cannot be edited from the application.</p></div>
    </header>
    <section class="patient-card">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="lg:col-span-2"><label for="audit-search" class="patient-label">Search record description or ID</label><input id="audit-search" type="search" wire:model.live.debounce.300ms="search" maxlength="255" class="patient-input w-full" placeholder="Search audit history..."></div>
            <div><label for="audit-user" class="patient-label">User</label><select id="audit-user" wire:model.live="userFilter" class="patient-input w-full"><option value="">All users</option>@foreach ($this->users as $user)<option value="{{ $user->user_id }}">{{ $user->name }}</option>@endforeach</select></div>
            <div><label for="audit-action" class="patient-label">Action</label><select id="audit-action" wire:model.live="actionFilter" class="patient-input w-full"><option value="">All actions</option><option value="UPDATE">UPDATE</option><option value="DELETE">DELETE</option></select></div>
            <div><label for="audit-module" class="patient-label">Module</label><select id="audit-module" wire:model.live="moduleFilter" class="patient-input w-full"><option value="">All modules</option>@foreach ($this->modules as $module)<option value="{{ $module }}">{{ $module }}</option>@endforeach</select></div>
            <div><label for="audit-from" class="patient-label">From date</label><input id="audit-from" type="date" wire:model.live="from" class="patient-input w-full"></div>
            <div><label for="audit-to" class="patient-label">To date</label><input id="audit-to" type="date" wire:model.live="to" class="patient-input w-full"></div>
        </div>
        <button type="button" wire:click="clearFilters" class="patient-button mt-4 text-xs">Clear filters</button>
        @php($logs = $this->auditLogs)
        <div class="mt-5 overflow-x-auto" wire:loading.class="opacity-50">
            <table class="w-full min-w-[760px] text-left text-xs">
                <caption class="sr-only">Audit log entries</caption>
                <thead class="bg-blush"><tr><th scope="col" class="px-3 py-3">Date and Time</th><th scope="col" class="px-3 py-3">User</th><th scope="col" class="px-3 py-3">Action</th><th scope="col" class="px-3 py-3">Module</th><th scope="col" class="px-3 py-3">Record Description</th><th scope="col" class="px-3 py-3">Details</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($logs as $log)
                        <tr wire:key="audit-{{ $log->id }}">
                            <td class="whitespace-nowrap px-3 py-4">{{ $log->created_at?->setTimezone('Asia/Manila')->format('Y-m-d H:i:s') }}</td>
                            <td class="px-3 py-4"><span class="block font-semibold">{{ $log->user_name }}</span><span class="text-muted">ID: {{ $log->user_id ?? 'Deleted account' }}</span></td>
                            <td class="px-3 py-4"><span @class(['rounded px-2 py-1 text-[10px] font-semibold', 'bg-blue-100 text-blue-800' => $log->action === 'UPDATE', 'bg-red-100 text-red-800' => $log->action === 'DELETE'])>{{ $log->action }}</span></td>
                            <td class="px-3 py-4">{{ $log->module }}</td>
                            <td class="px-3 py-4"><span class="font-semibold">{{ $log->record_description }}</span><span class="mt-1 block text-muted">{{ $log->record_type }} #{{ $log->record_id ?? 'n/a' }}</span></td>
                            <td class="px-3 py-4"><button type="button" wire:click="viewDetails({{ $log->id }})" class="patient-button px-3 py-2 text-xs">View Details</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-muted">No audit entries match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $logs->links() }}</div>
    </section>
    @if ($this->selectedAudit)
        @php($detail = $this->selectedAudit)
        <div x-data x-on:keydown.escape.window="$wire.closeDetails()" x-trap.inert.noscroll="true" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="audit-detail-title">
            <div class="flex min-h-screen items-center justify-center bg-gray-900/50 px-4 py-8" wire:click.self="closeDetails">
                <section class="patient-card w-full max-w-3xl">
                    <div class="flex items-start justify-between gap-4"><div><h2 id="audit-detail-title" class="text-base font-semibold">Audit Details</h2><p class="mt-1 text-xs text-muted">{{ $detail->record_description }}</p></div><button type="button" wire:click="closeDetails" class="patient-button text-xs">Close</button></div>
                    <dl class="mt-5 grid gap-3 text-xs sm:grid-cols-2">
                        <div><dt class="font-semibold">User</dt><dd class="mt-1 text-muted">{{ $detail->user_name }} (ID: {{ $detail->user_id ?? 'Deleted account' }})</dd></div>
                        <div><dt class="font-semibold">Action</dt><dd class="mt-1"><span @class(['font-semibold text-blue-800' => $detail->action === 'UPDATE', 'font-semibold text-red-800' => $detail->action === 'DELETE'])>{{ $detail->action }}</span></dd></div>
                        <div><dt class="font-semibold">Module / Record</dt><dd class="mt-1 text-muted">{{ $detail->module }} / {{ $detail->record_type }} #{{ $detail->record_id ?? 'n/a' }}</dd></div>
                        <div><dt class="font-semibold">Date and Time</dt><dd class="mt-1 text-muted">{{ $detail->created_at?->setTimezone('Asia/Manila')->format('Y-m-d H:i:s') }}</dd></div>
                    </dl>
                    <div class="mt-5 grid gap-4 text-xs sm:grid-cols-3">
                        <div><h3 class="font-semibold">Changed Fields</h3><ul class="mt-2 space-y-1 text-muted">@forelse ($detail->changed_fields ?? [] as $field)<li>{{ $field }}</li>@empty<li>None</li>@endforelse</ul></div>
                        <div><h3 class="font-semibold">Previous Values</h3><pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words rounded bg-blush p-3 text-[10px]">{{ json_encode($detail->previous_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
                        <div><h3 class="font-semibold">New Values</h3><pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap break-words rounded bg-blush p-3 text-[10px]">{{ json_encode($detail->new_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
                    </div>
                </section>
            </div>
        </div>
    @endif
</div>
