<?php

namespace App\Livewire\Attendance;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Employee;
use App\Services\AttendanceProcessor;
use App\Services\PunchImporter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Import punches')]
class Import extends Component
{
    use AuthorizesPermission, WithFileUploads;

    public $file = null;

    public ?array $result = null;

    public function mount(): void
    {
        $this->allow('attendance.manage');
    }

    public function import(PunchImporter $importer, AttendanceProcessor $processor): void
    {
        $this->allow('attendance.manage');
        $this->validate(['file' => 'required|file|max:10240|mimes:csv,txt']);

        $result = $importer->import($this->file->getRealPath());

        $processed = 0;
        if ($result['imported'] > 0) {
            $processed = $processor->processRange($result['from'], $result['to'], Employee::whereIn('id', $result['employee_ids'])->get());
        }

        activity()->causedBy(auth()->user())->withProperties([
            'imported' => $result['imported'], 'duplicates' => $result['duplicates'], 'errors' => count($result['errors']),
        ])->log('punch import');

        $this->result = [
            'imported' => $result['imported'], 'duplicates' => $result['duplicates'],
            'errors' => array_slice($result['errors'], 0, 50), 'error_count' => count($result['errors']), 'processed' => $processed,
        ];
        $this->reset('file');
    }

    public function render()
    {
        return view('livewire.attendance.import');
    }
}
