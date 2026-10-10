@php
    $fmt = fn ($m) => $m ? sprintf('%d:%02d', intdiv($m, 60), $m % 60) : '—';
    $badge = fn ($s) => match ($s) { 'present' => 'bg-green-100 text-green-800', 'absent' => 'bg-red-100 text-red-800', 'leave' => 'bg-blue-100 text-blue-800', default => 'bg-gray-100 text-gray-600' };
@endphp
<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <x-page-header title="My attendance"><x-text-input type="month" wire:model.live="month" /></x-page-header>

    <div class="mb-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-5">
        @foreach ($totals as $label => $value)<div class="rounded-lg bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">{{ $label }}</div><div class="text-lg font-semibold">{{ $value }}</div></div>@endforeach
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">In</th><th class="px-4 py-3">Out</th><th class="px-4 py-3">Late</th><th class="px-4 py-3">Early</th><th class="px-4 py-3">OT</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($records as $r)
                    <tr wire:key="pa-{{ $r->id }}">
                        <td class="px-4 py-3">{{ $r->date->format('D, d M') }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $badge($r->status) }}">{{ ucfirst($r->status) }}</span></td>
                        <td class="px-4 py-3">{{ $r->first_in?->format('H:i') ?? '—' }}</td><td class="px-4 py-3">{{ $r->last_out?->format('H:i') ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $fmt($r->late_minutes) }}</td><td class="px-4 py-3">{{ $fmt($r->early_minutes) }}</td><td class="px-4 py-3">{{ $fmt($r->ot_minutes) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No attendance recorded for this month.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-gray-500">Something wrong? Ask your manager or HR to submit an attendance adjustment.</p>
</div>
