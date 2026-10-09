<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Patient Table</h1>
        <p class="mt-2 text-sm text-gray-600">Search and review registered patient information.</p>
    </div>

    <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="w-full sm:max-w-md">
                <label for="patient-table-search" class="mb-1 block text-sm font-medium text-gray-700">Search patients</label>
                <input id="patient-table-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Patient ID, name, or contact number" class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
            </div>
            <p class="text-sm text-gray-500">{{ $this->patients->total() }} {{ $this->patients->total() === 1 ? 'patient' : 'patients' }}</p>
        </div>

        @if ($this->patients->isEmpty())
            <p class="rounded-md border border-dashed border-gray-300 px-4 py-10 text-center text-sm text-gray-500">No patients found.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                    <thead>
                        <tr class="text-xs uppercase text-gray-500">
                            <th class="px-3 py-3">Patient ID</th>
                            <th class="px-3 py-3">Name</th>
                            <th class="px-3 py-3">Sex</th>
                            <th class="px-3 py-3">Age</th>
                            <th class="px-3 py-3">Contact Number</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($this->patients as $patient)
                            <tr wire:key="patient-{{ $patient->patient_id }}">
                                <td class="whitespace-nowrap px-3 py-3 text-gray-700">{{ $patient->patient_id }}</td>
                                <td class="px-3 py-3 font-medium text-gray-900">{{ trim(implode(' ', array_filter([$patient->last_name.',', $patient->first_name, $patient->middle_name]))) ?: 'Not available' }}</td>
                                <td class="px-3 py-3 text-gray-700">{{ $patient->sex ?: 'Not available' }}</td>
                                <td class="px-3 py-3 text-gray-700">{{ $patient->age ?? 'Not available' }}</td>
                                <td class="px-3 py-3 text-gray-700">{{ $patient->contact_number ?: 'Not available' }}</td>
                                <td class="px-3 py-3 text-gray-700">{{ $patient->status ?: 'Not available' }}</td>
                                <td class="px-3 py-3"><button type="button" wire:click="viewPatient({{ $patient->patient_id }})" class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-500">View</button>
                                    <button type="button" wire:click="generateIdCard({{ $patient->patient_id }})" wire:loading.attr="disabled" wire:target="generateIdCard" class="mt-1 whitespace-nowrap rounded-md border border-blue-600 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50 disabled:opacity-50">Generate ID Card</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-5">{{ $this->patients->links() }}</div>
        @endif
    </section>

    @if ($showPatientModal && $this->selectedPatient)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="patient-modal-title">
            <div class="flex min-h-screen items-center justify-center bg-gray-900/50 px-4 py-8">
                <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
                        <div>
                            <h2 id="patient-modal-title" class="text-lg font-semibold text-gray-900">Patient Details</h2>
                            <p class="mt-1 text-sm text-gray-500">Patient #{{ $this->selectedPatient->patient_id }}</p>
                        </div>
                        <button type="button" wire:click="closePatientModal" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Close</button>
                    </div>

                    <div class="mt-5 grid gap-6 md:grid-cols-2">
                        <div>
                            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Patient Information</h3>
                            <dl class="space-y-1 text-sm text-gray-700">
                                <div><dt class="inline font-medium">Name:</dt> <dd class="inline">{{ trim(implode(' ', array_filter([$this->selectedPatient->first_name, $this->selectedPatient->middle_name, $this->selectedPatient->last_name]))) ?: 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Birthdate:</dt> <dd class="inline">{{ $this->selectedPatient->birthdate ?: 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Age:</dt> <dd class="inline">{{ $this->selectedPatient->age ?? 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Sex:</dt> <dd class="inline">{{ $this->selectedPatient->sex ?: 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Contact number:</dt> <dd class="inline">{{ $this->selectedPatient->contact_number ?: 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Emergency contact:</dt> <dd class="inline">{{ $this->selectedPatient->emergency_contact ?: 'Not available' }}</dd></div>
                            </dl>
                        </div>
                        <div>
                            <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Registration</h3>
                            <dl class="space-y-1 text-sm text-gray-700">
                                <div><dt class="inline font-medium">Address:</dt> <dd class="inline">{{ $this->selectedPatient->address ?: 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Registration date:</dt> <dd class="inline">{{ $this->selectedPatient->registration_date ?: 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Status:</dt> <dd class="inline">{{ $this->selectedPatient->status ?: 'Not available' }}</dd></div>
                                <div><dt class="inline font-medium">Vital signs recorded:</dt> <dd class="inline">{{ $this->selectedPatient->vitalSigns->count() }}</dd></div>
                                <div><dt class="inline font-medium">Medical records:</dt> <dd class="inline">{{ $this->selectedPatient->medicalRecords->count() }}</dd></div>
                            </dl>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-gray-200 pt-5">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Recent Vital Signs</h3>
                        @forelse ($this->selectedPatient->vitalSigns->sortByDesc('recorded_at')->take(3) as $vitalSign)
                            <div class="border-b border-gray-100 py-2 text-sm text-gray-700 last:border-b-0"><span class="font-medium">{{ $vitalSign->recorded_at?->format('M j, Y g:i A') ?: 'Not available' }}</span> · BP {{ $vitalSign->blood_pressure ?: 'Not available' }} · Temp {{ $vitalSign->temperature ?: 'Not available' }} · Recorded by {{ $vitalSign->recordedBy?->name ?: 'Not available' }}</div>
                        @empty
                            <p class="text-sm text-gray-500">No vital signs recorded.</p>
                        @endforelse
                    </div>

                    <div class="mt-6 border-t border-gray-200 pt-5">
                        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Medical Records</h3>
                        @forelse ($this->selectedPatient->medicalRecords->sortByDesc('consultation_date')->take(3) as $medicalRecord)
                            <div class="border-b border-gray-100 py-2 text-sm text-gray-700 last:border-b-0"><span class="font-medium">{{ $medicalRecord->consultation_date?->format('M j, Y') ?: 'Not available' }}</span> · {{ $medicalRecord->diagnosis ?: 'Not available' }} · Handled by {{ $medicalRecord->handledBy?->name ?: 'Not available' }}</div>
                        @empty
                            <p class="text-sm text-gray-500">No medical records found.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endif
    @if ($showCardModal && $cardPatientId)
        <div wire:key="patient-card-modal-{{ $cardPatientId }}" x-data="{ cardReady: false }" x-init="$nextTick(() => $refs.close.focus())" x-trap.inert.noscroll="true" x-on:keydown.escape.window="$wire.closeCardModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-gray-900/50 px-4 py-8" role="dialog" aria-modal="true" aria-labelledby="card-modal-title" wire:click.self="closeCardModal">
            <section class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                <div class="mb-5 flex items-center justify-between gap-4">
                    <h2 id="card-modal-title" class="text-lg font-semibold text-gray-900">Patient Identification Card</h2>
                    <button x-ref="close" type="button" wire:click="closeCardModal" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Close</button>
                </div>
                <div class="overflow-x-auto rounded-lg bg-gray-100 p-4">
                    <iframe x-ref="card" x-on:load="cardReady = true" src="{{ route('patients.card.show', $cardPatientId) }}" title="Patient {{ $cardPatientId }} healthcare card preview" class="mx-auto block border-0 bg-white shadow" style="width: 85.60mm; height: 53.98mm;"></iframe>
                </div>
                <p class="mt-3 text-xs text-gray-500">Card size: 85.60 × 53.98 mm. Print at actual size (100%) with browser headers and footers disabled.</p>
                <div class="mt-5 flex flex-wrap justify-end gap-3">
                    <a href="{{ route('patients.card.download', $cardPatientId) }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500">Download PDF</a>
                    <button type="button" x-bind:disabled="!cardReady" x-on:click="$refs.card.contentWindow.focus(); $refs.card.contentWindow.print()" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50">Print</button>
                </div>
            </section>
        </div>
    @endif
</div>