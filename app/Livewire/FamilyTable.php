<?php

namespace App\Livewire;

use App\Models\Household;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Family Records')]
class FamilyTable extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $memberFilter = '';

    public function mount(): void
    {
        Gate::authorize('queue-view');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'memberFilter'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        Gate::authorize('queue-view');
        $this->reset('search', 'memberFilter');
        $this->resetPage();
    }

    #[Computed]
    public function households(): LengthAwarePaginator
    {
        Gate::authorize('queue-view');
        $search = trim($this->search);

        return Household::query()->withCount('members')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('family_number', 'like', '%'.$search.'%')
                        ->orWhere('label', 'like', '%'.$search.'%')
                        ->orWhere('address', 'like', '%'.$search.'%');
                });
            })
            ->when($this->memberFilter === 'with-members', fn (Builder $query): Builder => $query->has('members'))
            ->when($this->memberFilter === 'without-members', fn (Builder $query): Builder => $query->doesntHave('members'))
            ->orderBy('label')->orderBy('id')->paginate(10);
    }

    public function render(): View
    {
        Gate::authorize('queue-view');

        return view('livewire.family-table');
    }
}
