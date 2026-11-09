<div class="relative mx-auto max-w-[1440px] px-4 py-7 text-ink sm:px-7 lg:px-8 lg:py-8">
    <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-2xl font-semibold tracking-tight">Diagnosis Management</h1><p class="mt-2 text-sm text-muted">Maintain the catalog and review newly proposed diagnoses.</p></div>
        <button type="button" wire:click="createDiagnosis" wire:loading.attr="disabled" class="patient-button bg-brand-pink text-xs">Add Diagnosis</button>
    </header>
    @if ($success)<p role="status" class="mb-5 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ $success }}</p>@endif
    @if ($errors->any())<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="patient-card">
        <div class="grid gap-4 sm:grid-cols-2">
            <div><label for="diagnosis-directory-search" class="patient-label">Search Diagnosis</label><input id="diagnosis-directory-search" type="search" wire:model.live.debounce.300ms="search" class="patient-input w-full" maxlength="255"></div>
            <div><label for="diagnosis-review-filter" class="patient-label">Review status</label><select id="diagnosis-review-filter" wire:model.live="statusFilter" class="patient-input w-full"><option value="">All diagnoses</option><option value="approved">Approved catalog</option><option value="pending">Newly proposed diagnoses</option><option value="rejected">Rejected proposals</option></select></div>
        </div>
        @php($diagnoses = $this->diagnoses)
        <div class="mt-5 overflow-x-auto">
            <table class="w-full min-w-[650px] text-left text-xs">
                <thead class="bg-blush"><tr><th scope="col" class="px-3 py-3">Diagnosis / Code</th><th scope="col" class="px-3 py-3">Description</th><th scope="col" class="px-3 py-3">Status / Records</th><th scope="col" class="px-3 py-3">Actions</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @forelse ($diagnoses as $diagnosis)
                        <tr wire:key="diagnosis-{{ $diagnosis->id }}">
                            <td class="px-3 py-4"><p class="font-semibold">{{ $diagnosis->name }}</p><p class="mt-1 text-muted">{{ $diagnosis->code ?: 'No code' }}</p></td>
                            <td class="max-w-xs whitespace-pre-line break-words px-3 py-4">{{ $diagnosis->description ?: 'No description' }}</td>
                            <td class="px-3 py-4"><span class="rounded bg-brand-blue px-2 py-1">{{ ucfirst($diagnosis->review_status) }} &middot; {{ $diagnosis->is_active ? 'Active' : 'Inactive' }}</span><p class="mt-2 text-muted">{{ $diagnosis->medical_records_count }} medical records</p></td>
                            <td class="px-3 py-4"><div class="flex flex-wrap gap-2">
                                <button type="button" wire:click="editDiagnosis({{ $diagnosis->id }})" wire:loading.attr="disabled" class="patient-button px-3 py-2 text-xs">View / Edit</button>
                                @if ($diagnosis->review_status === 'pending')
                                    <button type="button" wire:click="reviewDiagnosis({{ $diagnosis->id }}, 'approved')" wire:confirm="Approve this diagnosis for the catalog?" wire:loading.attr="disabled" class="patient-button bg-brand-blue px-3 py-2 text-xs">Approve</button>
                                    <button type="button" wire:click="reviewDiagnosis({{ $diagnosis->id }}, 'rejected')" wire:confirm="Reject this proposal? Existing medical records will be preserved." wire:loading.attr="disabled" class="patient-button px-3 py-2 text-xs">Reject</button>
                                @elseif ($diagnosis->review_status === 'approved')
                                    <button type="button" wire:click="setActive({{ $diagnosis->id }}, {{ $diagnosis->is_active ? 'false' : 'true' }})" wire:confirm="Change this diagnosis's availability for future consultations?" wire:loading.attr="disabled" class="patient-button px-3 py-2 text-xs">{{ $diagnosis->is_active ? 'Deactivate' : 'Activate' }}</button>
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-8 text-center text-muted">No diagnoses match your search.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $diagnoses->links() }}</div>
    </section>
    @if ($showForm)
        <div x-data x-init="$nextTick(() => $refs.diagnosisName.focus())" x-on:keydown.escape.window="$wire.closeForm()" x-trap.inert.noscroll="true" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="diagnosis-form-title">
            <div class="flex min-h-screen items-center justify-center bg-gray-900/50 px-4 py-8" wire:click.self="closeForm">
                <section class="patient-card w-full max-w-lg">
                    <div class="flex items-center justify-between gap-4"><h2 id="diagnosis-form-title" class="text-base font-semibold">{{ $editingDiagnosisId ? 'View / Edit Diagnosis' : 'Add Diagnosis' }}</h2><button type="button" wire:click="closeForm" class="patient-button text-xs">Close</button></div>
                    <form wire:submit="saveDiagnosis" class="mt-5 space-y-4">
                        <div><label for="catalog-diagnosis-name" class="patient-label">Name *</label><input x-ref="diagnosisName" id="catalog-diagnosis-name" wire:model="name" required maxlength="255" class="patient-input w-full"><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
                        <div><label for="catalog-diagnosis-code" class="patient-label">Code (optional)</label><input id="catalog-diagnosis-code" wire:model="code" maxlength="50" class="patient-input w-full"><x-input-error :messages="$errors->get('code')" class="mt-2" /></div>
                        <div><label for="catalog-diagnosis-description" class="patient-label">Description (optional)</label><textarea id="catalog-diagnosis-description" wire:model="description" rows="4" maxlength="10000" class="patient-input w-full"></textarea><x-input-error :messages="$errors->get('description')" class="mt-2" /></div>
                        <p class="text-xs text-muted">Catalog changes apply to future selections. Saved consultation wording is preserved.</p>
                        <button type="submit" wire:loading.attr="disabled" class="patient-button bg-brand-pink text-xs">Save Diagnosis</button>
                    </form>
                </section>
            </div>
        </div>
    @endif
</div>
