<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Employee;
use App\Models\Notice;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Notices')]
class Notices extends Component
{
    use AuthorizesPermission, WithPagination;

    public function mount(): void
    {
        $this->allow('notices.view');
    }

    public function render()
    {
        $department = Employee::where('user_id', auth()->id())->value('department_id');

        return view('livewire.portal.notices', [
            'notices' => Notice::visibleTo($department)->with('department')->orderByDesc('is_pinned')->latest('published_at')->latest('id')->paginate(10),
        ]);
    }
}
