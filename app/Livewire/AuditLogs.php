<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class AuditLogs extends Component
{
    use WithPagination;

    public string $search = '';

    public string $userFilter = '';

    public string $actionFilter = '';

    public string $moduleFilter = '';

    public string $from = '';

    public string $to = '';

    public ?int $selectedAuditId = null;

    public function mount(): void
    {
        Gate::authorize('audit-log-view');
    }

    public function updated(string $property): void
    {
        Gate::authorize('audit-log-view');
        if (in_array($property, ['search', 'userFilter', 'actionFilter', 'moduleFilter', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function viewDetails(int $auditId): void
    {
        Gate::authorize('audit-log-view');
        AuditLog::findOrFail($auditId);
        $this->selectedAuditId = $auditId;
    }

    public function closeDetails(): void
    {
        Gate::authorize('audit-log-view');
        $this->selectedAuditId = null;
    }

    public function clearFilters(): void
    {
        Gate::authorize('audit-log-view');
        $this->reset('search', 'userFilter', 'actionFilter', 'moduleFilter', 'from', 'to');
        $this->resetPage();
    }

    #[Computed]
    public function auditLogs(): LengthAwarePaginator
    {
        Gate::authorize('audit-log-view');

        return AuditLog::query()
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $search = mb_substr(trim($this->search), 0, 255);
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('record_description', 'like', '%'.$search.'%')
                        ->orWhere('record_id', 'like', '%'.$search.'%')
                        ->orWhere('record_type', 'like', '%'.$search.'%');
                });
            })
            ->when($this->userFilter !== '', fn (Builder $query): Builder => $query->where('user_id', (int) $this->userFilter))
            ->when(in_array($this->actionFilter, ['UPDATE', 'DELETE'], true), fn (Builder $query): Builder => $query->where('action', $this->actionFilter))
            ->when($this->moduleFilter !== '', fn (Builder $query): Builder => $query->where('module', $this->moduleFilter))
            ->when($this->from !== '', fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $this->to))
            ->latest('created_at')->latest('id')->paginate(15);
    }

    #[Computed]
    public function modules(): array
    {
        Gate::authorize('audit-log-view');

        return AuditLog::query()->distinct()->orderBy('module')->pluck('module')->all();
    }

    #[Computed]
    public function users(): Collection
    {
        Gate::authorize('audit-log-view');
        $ids = AuditLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id');

        return User::query()->whereIn('user_id', $ids)->orderBy('name')->get(['user_id', 'name']);
    }

    #[Computed]
    public function selectedAudit(): ?AuditLog
    {
        Gate::authorize('audit-log-view');

        return $this->selectedAuditId === null ? null : AuditLog::findOrFail($this->selectedAuditId);
    }

    public function render(): View
    {
        return view('livewire.audit-logs');
    }
}
