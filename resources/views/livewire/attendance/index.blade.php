@php
    $fmt = fn ($m) => $m ? sprintf('%d:%02d', intdiv($m, 60), $m % 60) : '—';
    $badge = fn ($s) => match ($s) {
        'present' => 'bg-green-100 text-green-800', 'absent' => 'bg-red-100 text-red-800',
        'leave' => 'bg-blue-100 text-blue-800', default => 'bg-zinc-100 text-zinc-600',
    };
@endphp
<div class="max-w-7xl">
    <x-page-header title="Attendance">
        @if ($canProcess && $tab === 'daily')
            <x-secondary-button wire:click="processDate">Process this date</x-secondary-button>
        @endif
        @can('attendance.manage')
            <flux:button variant="primary" size="sm" href="{{ route('attendance.import') }}" wire:navigate>Import punches</flux:button>
        @endcan
    </x-page-header>
    <x-flash />

    <div class="mb-4 flex gap-6 border-b border-zinc-200 text-sm">
        @foreach (['daily' => 'Daily', 'monthly' => 'Monthly by employee'] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" class="-mb-px border-b-2 px-1 pb-2 {{ $tab === $key ? 'border-indigo-500 font-medium text-zinc-900' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'daily')
        <div class="mb-4 grid gap-3 sm:grid-cols-4">
            <x-text-input type="date" wire:model.live="date" />
            <x-text-input wire:model.live.debounce.300ms="search" placeholder="Search name, code…" />
            <select wire:model.live="department" class="rounded-lg border-zinc-300 text-sm shadow-xs">
                <option value="">All departments</option>
                @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
            </select>
            <select wire:model.live="statusFilter" class="rounded-lg border-zinc-300 text-sm shadow-xs">
                <option value="">All</option>
                <option value="present">Present</option><option value="absent">Absent</option><option value="leave">On leave</option>
                <option value="late">Late</option><option value="early">Early departure</option><option value="ot">Overtime</option><option value="missing">Missing punch</option>
            </select>
        </div>

        <div class="border border-zinc-200 overflow-x-auto rounded-xl bg-white">
            <table class="min-w-full divide-y divide-zinc-200 text-sm">
                <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500">
                    <tr><th class="px-4 py-3">Employee</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">In</th><th class="px-4 py-3">Out</th><th class="px-4 py-3">Late</th><th class="px-4 py-3">Early</th><th class="px-4 py-3">OT</th><th class="px-4 py-3">Flags</th></tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($rows as $e)
                        @php $r = $e->attendanceRecords->first(); @endphp
                        <tr wire:key="att-{{ $e->id }}">
                            <td class="px-4 py-3"><span class="font-mono text-xs text-zinc-500">{{ $e->employee_no }}</span> {{ $e->full_name }}<div class="text-xs text-zinc-400">{{ $e->department->name }}</div></td>
                            @if ($r)
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $badge($r->status) }}">{{ ucfirst($r->status) }}@if ($r->leave_fraction > 0 && $r->leave_fraction < 1) (½)@endif</span></td>
                                <td class="px-4 py-3">{{ $r->first_in?->format('H:i') ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $r->last_out?->format('H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 {{ $r->late_minutes ? 'text-amber-700' : '' }}">{{ $fmt($r->late_minutes) }}</td>
                                <td class="px-4 py-3 {{ $r->early_minutes ? 'text-amber-700' : '' }}">{{ $fmt($r->early_minutes) }}</td>
                                <td class="px-4 py-3">{{ $fmt($r->ot_minutes) }}</td>
                                <td class="px-4 py-3 text-xs text-zinc-500">{{ $r->missing_punch ? 'Missing punch ' : '' }}{{ $r->is_adjusted ? 'Adjusted' : '' }}</td>
                            @else
                                <td class="px-4 py-3 text-zinc-400" colspan="7">Not processed</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-8 text-center text-zinc-500">No employees found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    @else
        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <select wire:model.live="employeeId" class="rounded-lg border-zinc-300 text-sm shadow-xs">
                <option value="">Select employee…</option>
                @foreach ($employees as $o)<option value="{{ $o->id }}">{{ $o->employee_no }} — {{ $o->full_name }}</option>@endforeach
            </select>
            <x-text-input type="month" wire:model.live="month" />
        </div>

        @if ($employee)
            <div class="mb-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-6">
                @foreach (['Present days' => $totals['present'], 'Absent days' => $totals['absent'], 'Leave days' => $totals['leave'], 'Late (days)' => $totals['late'], 'Late (hh:mm)' => $fmt($totals['late_minutes']), 'OT (hh:mm)' => $fmt($totals['ot_minutes'])] as $label => $value)
                    <div class="border border-zinc-200 rounded-xl bg-white p-3"><div class="text-xs text-zinc-500">{{ $label }}</div><div class="text-lg font-semibold">{{ $value }}</div></div>
                @endforeach
            </div>
            <div class="border border-zinc-200 overflow-x-auto rounded-xl bg-white">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">In</th><th class="px-4 py-3">Out</th><th class="px-4 py-3">Late</th><th class="px-4 py-3">Early</th><th class="px-4 py-3">OT</th></tr></thead>
                    <tbody class="divide-y divide-zinc-100">
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
                            <tr><td colspan="7" class="px-4 py-8 text-center text-zinc-500">No processed records for this month.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-zinc-500">Choose an employee to see their month.</p>
        @endif
    @endif
</div>
