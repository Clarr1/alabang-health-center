<section wire:poll.10s aria-label="Today's queue statistics">
    @php
        $waitingForAssessment = $statistics['Waiting Patients'] + $statistics['Patients Called'];
        $readyForConsultation = $statistics['Patients Ready for Consultation'];
        $cards = [
            ["Today's arrivals", $statistics['Total Patients Queued Today'], now('Asia/Manila')->format('d F Y')],
            ['Waiting', $waitingForAssessment + $readyForConsultation, $waitingForAssessment.' assessment · '.$readyForConsultation.' ready'],
            ['In assessment', $statistics['Patients Undergoing Vital Signs'], 'Vital signs in progress'],
            ['In consultation', $statistics['Patients In Consultation'], 'Consultations in progress'],
            ['Completed', $statistics['Completed Patients'], 'Visits completed today'],
        ];
    @endphp
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        @foreach ($cards as [$label, $count, $description])
            <div class="rounded-xl border border-line bg-white px-4 py-5 last:max-lg:col-span-2">
                <p class="text-xs text-muted">{{ $label }}</p>
                <p class="mt-2 text-[28px] font-semibold leading-none tracking-tight text-ink">{{ number_format($count) }}</p>
                <p class="mt-3 text-[11px] leading-relaxed text-muted">{{ $description }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-5 grid gap-4 md:grid-cols-2">
        @foreach (['Assessment' => $assessmentQueues, 'Consultation' => $consultationQueues] as $area => $serving)
            <section class="patient-card" aria-label="{{ $area }} currently serving">
                <h2 class="text-sm font-semibold">{{ $area }}</h2>
                @if ($serving->isNotEmpty())
                    <div class="mt-4 flex items-center justify-between gap-4">
                        <p class="text-2xl font-semibold tracking-wide">{{ $serving->first()->formattedQueueNumber() }}</p>
                        <p class="text-right text-xs">{{ trim(implode(' ', array_filter([$serving->first()->patient?->first_name, $serving->first()->patient?->middle_name, $serving->first()->patient?->last_name]))) ?: 'Patient unavailable' }}</p>
                    </div>
                    <p class="mt-4 text-[11px] text-muted">{{ $serving->count() }} {{ $serving->count() === 1 ? 'patient' : 'patients' }} {{ $area === 'Assessment' ? 'called or in assessment' : 'in consultation' }} today</p>
                    @if ($serving->count() > 1)
                        <p class="mt-2 text-[11px] text-muted">Also serving: {{ $serving->skip(1)->map(fn ($queue) => $queue->formattedQueueNumber())->join(', ') }}</p>
                    @endif
                @else
                    <p class="mt-4 text-lg font-semibold text-muted">No patient being served</p>
                    <p class="mt-4 text-[11px] text-muted">{{ $area === 'Assessment' ? 'Called patients and vital signs visits will appear here.' : 'Active consultations will appear here.' }}</p>
                @endif
            </section>
        @endforeach
    </div>
</section>
