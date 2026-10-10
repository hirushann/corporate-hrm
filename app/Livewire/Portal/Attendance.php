<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\ResolvesEmployee;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My attendance')]
class Attendance extends Component
{
    use ResolvesEmployee;

    #[Url]
    public string $month = '';

    public function mount(): void
    {
        $this->month = $this->month ?: today()->format('Y-m');
    }

    public function render()
    {
        $employee = $this->myEmployee();

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month)) {
            $this->month = today()->format('Y-m');
        }
        $start = Carbon::parse($this->month.'-01');

        $records = $employee->attendanceRecords()->whereBetween('date', [$start, $start->copy()->endOfMonth()])->orderBy('date')->get();

        return view('livewire.portal.attendance', [
            'records' => $records,
            'totals' => [
                'Present days' => $records->where('status', 'present')->count(),
                'Absent days' => $records->where('status', 'absent')->count(),
                'Leave days' => (float) $records->where('status', 'leave')->sum('leave_fraction'),
                'Late arrivals' => $records->where('late_minutes', '>', 0)->count(),
                'Overtime (hh:mm)' => sprintf('%d:%02d', intdiv($records->sum('ot_minutes'), 60), $records->sum('ot_minutes') % 60),
            ],
        ]);
    }
}
