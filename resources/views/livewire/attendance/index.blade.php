@php
    $fmt = fn ($m) => $m ? sprintf('%d:%02d', intdiv($m, 60), $m % 60) : '—';
    $badge = fn ($s) => match ($s) {
        'present' => 'bg-green-100 text-green-800', 'absent' => 'bg-red-100 text-red-800',
        'leave' => 'bg-blue-100 text-blue-800', default => 'bg-gray-100 text-gray-600',
    };
@endphp
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <x-page-header title="Attendance">
        @if ($canProcess && $tab === 'daily')
            <x-secondary-button wire:click="processDate">Process this date</x-secondary-button>
        @endif
        @can('attendance.manage')
            <a href="{{ route('attendance.import') }}" wire:navigate class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">Import punches</a>
        @endcan
    </x-page-header>
    <x-flash />

    <div class="mb-4 flex gap-6 border-b border-gray-200 text-sm">
        @foreach (['daily' => 'Daily', 'monthly' => 'Monthly by employee'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" class="-mb-px border-b-2 px-1 pb-2 {{ $tab === $key ? 'border-indigo-500 font-medium text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'daily')
        <div class="mb-4 grid gap-3 sm:grid-cols-4">
            <x-text-input type="date" wire:model.live="date" />
            <x-text-input wire:model.live.debounce.300ms="search" placeholder="Search name, code…" />
            <select wire:model.live="department" class="rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">All departments</option>
                @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
            </select>
            <select wire:model.live="statusFilter" class="rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">All</option>
                <option value="present">Present</option><option value="absent">Absent</option><option value="leave">On leave</option>
                <option value="late">Late</option><option value="early">Early departure</option><option value="ot">Overtime</option><option value="missing">Missing punch</option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr><th class="px-4 py-3">Employee</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">In</th><th class="px-4 py-3">Out</th><th class="px-4 py-3">Late</th><th class="px-4 py-3">Early</th><th class="px-4 py-3">OT</th><th class="px-4 py-3">Flags</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($rows as $e)
                        @php $r = $e->attendanceRecords->first(); @endphp
                        <tr wire:key="att-{{ $e->id }}">
                            <td class="px-4 py-3"><span class="font-mono text-xs text-gray-500">{{ $e->employee_no }}</span> {{ $e->full_name }}<div class="text-xs text-gray-400">{{ $e->department->name }}</div></td>
                            @if ($r)
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $badge($r->status) }}">{{ ucfirst($r->status) }}@if ($r->leave_fraction > 0 && $r->leave_fraction < 1) (½)@endif</span></td>
                                <td class="px-4 py-3">{{ $r->first_in?->format('H:i') ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $r->last_out?->format('H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 {{ $r->late_minutes ? 'text-amber-700' : '' }}">{{ $fmt($r->late_minutes) }}</td>
                                <td class="px-4 py-3 {{ $r->early_minutes ? 'text-amber-700' : '' }}">{{ $fmt($r->early_minutes) }}</td>
                                <td class="px-4 py-3">{{ $fmt($r->ot_minutes) }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ $r->missing_punch ? 'Missing punch ' : '' }}{{ $r->is_adjusted ? 'Adjusted' : '' }}</td>
                            @else
                                <td class="px-4 py-3 text-gray-400" colspan="7">Not processed</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No employees found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    @else
        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <select wire:model.live="employeeId" class="rounded-md border-gray-300 text-sm shadow-sm">
                <option value="">Select employee…</option>
                @foreach ($employees as $o)<option value="{{ $o->id }}">{{ $o->employee_no }} — {{ $o->full_name }}</option>@endforeach
            </select>
            <x-text-input type="month" wire:model.live="month" />
        </div>

        @if ($employee)
            <div class="mb-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-6">
                @foreach (['Present days' => $totals['present'], 'Absent days' => $totals['absent'], 'Leave days' => $totals['leave'], 'Late (days)' => $totals['late'], 'Late (hh:mm)' => $fmt($totals['late_minutes']), 'OT (hh:mm)' => $fmt($totals['ot_minutes'])] as $label => $value)
                    <div class="rounded-lg bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">{{ $label }}</div><div class="text-lg font-semibold">{{ $value }}</div></div>
                @endforeach
            </div>
            <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">In</th><th class="px-4 py-3">Out</th><th class="px-4 py-3">Late</th><th class="px-4 py-3">Early</th><th class="px-4 py-3">OT</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($records as $r)
                            <tr wire:key="m-{{ $r->id }}">
                                <td class="px-4 py-3">{{ $r->date->format('D, d M') }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $badge($r->status) }}">{{ ucfirst($r->status) }}</span></td>
                                <td class="px-4 py-3">{{ $r->first_in?->format('H:i') ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $r->last_out?->format('H:i') ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $fmt($r->late_minutes) }}</td>
                                <td class="px-4 py-3">{{ $fmt($r->early_minutes) }}</td>
                                <td class="px-4 py-3">{{ $fmt($r->ot_minutes) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No processed records for this month.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">Choose an employee to see their month.</p>
        @endif
    @endif
</div>
