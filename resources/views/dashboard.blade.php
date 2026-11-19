<x-app-layout>
    <div class="mx-auto max-w-7xl space-y-6 px-4 py-7 sm:px-7 lg:px-8">
        <h1 class="text-2xl font-semibold">Dashboard</h1>
        <p class="text-sm text-muted">Welcome, {{ auth()->user()->name }}. Open a module to continue your work.</p>
        <div class="flex flex-wrap gap-3">
            @can('patient-records-view')
                <a href="{{ route('patients.index') }}" wire:navigate class="patient-button">Patient Record</a>
                <a href="{{ route('patient.information-form') }}" wire:navigate class="patient-button">Patient Registration</a>
            @endcan
            @can('queue-view')
                <a href="{{ route('queues.index') }}" wire:navigate class="patient-button">Queue Management</a>
            @endcan
            @can('medical-records-view')
                <a href="{{ route('medical-records.index') }}" wire:navigate class="patient-button">Medical Records</a>
            @endcan
            @can('queue-consult')
                <a href="{{ route('doctors-consultation.index') }}" wire:navigate class="patient-button">Doctor's Consultation</a>
            @endcan
            @can('follow-ups-manage')
                <a href="{{ route('follow-ups.index') }}" wire:navigate class="patient-button">Follow-ups</a>
            @endcan
            @can('surveillance-view')
                <a href="{{ route('surveillance.index') }}" wire:navigate class="patient-button">Syndromic Surveillance</a>
            @endcan
            @can('manage-accounts')
                <a href="{{ route('users-roles.index') }}" wire:navigate class="patient-button">Users &amp; Roles</a>
            @endcan
        </div>
        @can('queue-view')
            <livewire:queue-dashboard />
        @endcan
    </div>
</x-app-layout>
