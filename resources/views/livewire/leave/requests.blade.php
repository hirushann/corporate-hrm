<div class="max-w-6xl">
    <x-page-header title="Leave requests" />
    <x-flash />

    @if ($canManage)
        <form wire:submit="submit" class="border border-zinc-200 mb-6 grid gap-4 rounded-xl bg-white p-6 sm:grid-cols-4">
            <h2 class="font-medium text-zinc-900 sm:col-span-4">Record a leave request</h2>
            <x-field label="Employee" error="employeeId"><select wire:model.live="employeeId" class="mt-1 block w-full rounded-lg border-zinc-300 text-sm shadow-xs"><option value="">Select…</option>@foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->employee_no }} — {{ $e->full_name }}</option>@endforeach</select></x-field>
            <x-field label="Leave type" error="leaveTypeId"><select wire:model.live="leaveTypeId" class="mt-1 block w-full rounded-lg border-zinc-300 text-sm shadow-xs"><option value="">Select…</option>@foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></x-field>
            <x-field label="From" error="fromDate"><x-text-input type="date" wire:model.live="fromDate" class="mt-1 block w-full" /></x-field>
            <x-field label="To" error="toDate"><x-text-input type="date" wire:model="toDate" class="mt-1 block w-full" /></x-field>
            <x-field label="Reason" error="reason" class="sm:col-span-2"><x-text-input wire:model="reason" class="mt-1 block w-full" /></x-field>
            <label class="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" wire:model="halfDay" class="rounded border-zinc-300"> Half day</label>
            <div class="flex items-end"><x-primary-button>Submit</x-primary-button></div>
            @if ($preview)<p class="text-sm text-zinc-600 sm:col-span-4">Balance: {{ $preview }}</p>@endif
            @error('leave')<p class="text-sm text-red-600 sm:col-span-4">{{ $message }}</p>@enderror
        </form>
    @endif

    <select wire:model.live="statusFilter" class="mb-4 rounded-lg border-zinc-300 text-sm shadow-xs">
        <option value="">All</option>@foreach (['pending', 'approved', 'rejected', 'cancelled'] as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach
    </select>

    <div class="border border-zinc-200 overflow-x-auto rounded-xl bg-white">
        <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500"><tr><th class="px-4 py-3">Employee</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Dates</th><th class="px-4 py-3 text-right">Days</th><th class="px-4 py-3">Reason</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($requests as $r)
                    <tr wire:key="lr-{{ $r->id }}" class="align-top">
                        <td class="px-4 py-3">{{ $r->employee->employee_no }} {{ $r->employee->full_name }}</td>
                        <td class="px-4 py-3">{{ $r->leaveType->name }}</td>
                        <td class="px-4 py-3">{{ $r->from_date->format('Y-m-d') }}@if (! $r->from_date->isSameDay($r->to_date)) → {{ $r->to_date->format('Y-m-d') }}@endif</td>
                        <td class="px-4 py-3 text-right">{{ $r->days }}</td>
                        <td class="px-4 py-3">{{ $r->reason }}</td>
                        <td class="px-4 py-3">{{ ucfirst($r->status) }}@if ($r->decider)<div class="text-xs text-zinc-500">by {{ $r->decider->name }}@if ($r->decision_note): {{ $r->decision_note }}@endif</div>@endif</td>
                        <td class="space-x-3 px-4 py-3 text-right">
                            @if ($canApprove && $r->status === 'pending' && $r->employee->user_id !== auth()->id())
                                @if ($decidingId === $r->id)
                                    <x-text-input wire:model="decisionNote" placeholder="Note (optional)" class="mb-2 w-full" />
                                    <button wire:click="decide({{ $r->id }}, true)" class="text-green-700 hover:underline">Approve</button>
                                    <button wire:click="decide({{ $r->id }}, false)" class="text-red-700 hover:underline">Reject</button>
                                @else
                                    <button wire:click="$set('decidingId', {{ $r->id }})" class="text-indigo-600 hover:underline">Review</button>
                                @endif
                            @endif
                            @if ($canManage && in_array($r->status, ['pending', 'approved']))
                                <button wire:click="cancel({{ $r->id }})" wire:confirm="Cancel this leave request?" class="text-zinc-600 hover:underline">Cancel</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-zinc-500">No leave requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</div>
