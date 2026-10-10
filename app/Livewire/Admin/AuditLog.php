<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\AuthorizesPermission;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

#[Layout('layouts.app')]
#[Title('Audit log')]
class AuditLog extends Component
{
    use AuthorizesPermission, WithPagination;

    public string $subject = '';

    public string $event = '';

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->allow('audit.view');
    }

    public function updated($property): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $this->allow('audit.view');

        $activities = Activity::query()
            ->with('causer', 'subject')
            ->when($this->subject, fn ($q) => $q->where('subject_type', $this->subject))
            ->when($this->event, fn ($q) => $q->where('event', $this->event))
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->latest('id')
            ->paginate(20);

        return view('livewire.admin.audit-log', [
            'activities' => $activities,
            'subjectTypes' => Activity::query()->whereNotNull('subject_type')->distinct()->pluck('subject_type'),
        ]);
    }
}
