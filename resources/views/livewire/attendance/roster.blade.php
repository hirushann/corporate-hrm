<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <x-page-header title="Shift roster" />
    <x-flash />
    <p class="mb-4 text-sm text-gray-600">Employees without an assignment use the default shift ({{ $defaultShift?->name ?? 'none set' }}).</p>

    @if ($canManage)
        <form wire:submit="assign" class="mb-6 grid gap-4 rounded-lg bg-white p-6 shadow-sm sm:grid-cols-4">
            <x-field label="Shift" error="shiftId"><select wire:model="shiftId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"><option value="">Select…</option>@foreach ($shifts as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ substr($s->start_time, 0, 5) }}–{{ substr($s->end_time, 0, 5) }})</option>@endforeach</select></x-field>
            <x-field label="From" error="effectiveFrom"><x-text-input type="date" wire:model="effectiveFrom" class="mt-1 block w-full" /></x-field>
            <x-field label="To (optional)" error="effectiveTo"><x-text-input type="date" wire:model="effectiveTo" class="mt-1 block w-full" /></x-field>
            <div class="flex items-end"><x-primary-button>Assign to ticked employees</x-primary-button></div>
            @error('selected')<p class="text-sm text-red-600 sm:col-span-4">{{ $message }}</p>@enderror
        </form>
    @endif

    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <x-text-input wire:model.live.debounce.300ms="search" placeholder="Search name, code…" />
        <select wire:model.live="department" class="rounded-md border-gray-300 text-sm shadow-sm"><option value="">All departments</option>@foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr>@if ($canManage)<th class="px-4 py-3"></th>@endif<th class="px-4 py-3">Employee</th><th class="px-4 py-3">Department</th><th class="px-4 py-3">Current shift</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($employees as $e)
                    @php $a = $current->get($e->id); @endphp
                    <tr wire:key="ro-{{ $e->id }}">
                        @if ($canManage)<td class="px-4 py-3"><input type="checkbox" value="{{ $e->id }}" wire:model="selected" class="rounded border-gray-300"></td>@endif
                        <td class="px-4 py-3"><span class="font-mono text-xs text-gray-500">{{ $e->employee_no }}</span> {{ $e->full_name }}</td>
                        <td class="px-4 py-3">{{ $e->department->name }}</td>
                        <td class="px-4 py-3">{{ $a ? $a->shift->name.' (from '.$a->effective_from->format('Y-m-d').($a->effective_to ? ' to '.$a->effective_to->format('Y-m-d') : '').')' : ($defaultShift?->name.' (default)') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $employees->links() }}</div>
</div>
