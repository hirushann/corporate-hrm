<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <x-page-header title="Leave requests" />
    <x-flash />

    @if ($canManage)
        <form wire:submit="submit" class="mb-6 grid gap-4 rounded-lg bg-white p-6 shadow-sm sm:grid-cols-4">
            <h2 class="font-medium text-gray-900 sm:col-span-4">Record a leave request</h2>
            <x-field label="Employee" error="employeeId"><select wire:model.live="employeeId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"><option value="">Select…</option>@foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->employee_no }} — {{ $e->full_name }}</option>@endforeach</select></x-field>
            <x-field label="Leave type" error="leaveTypeId"><select wire:model.live="leaveTypeId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"><option value="">Select…</option>@foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></x-field>
            <x-field label="From" error="fromDate"><x-text-input type="date" wire:model.live="fromDate" class="mt-1 block w-full" /></x-field>
            <x-field label="To" error="toDate"><x-text-input type="date" wire:model="toDate" class="mt-1 block w-full" /></x-field>
            <x-field label="Reason" error="reason" class="sm:col-span-2"><x-text-input wire:model="reason" class="mt-1 block w-full" /></x-field>
            <label class="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" wire:model="halfDay" class="rounded border-gray-300"> Half day</label>
            <div class="flex items-end"><x-primary-button>Submit</x-primary-button></div>
            @if ($preview)<p class="text-sm text-gray-600 sm:col-span-4">Balance: {{ $preview }}</p>@endif
            @error('leave')<p class="text-sm text-red-600 sm:col-span-4">{{ $message }}</p>@enderror
        </form>
    @endif

    <select wire:model.live="statusFilter" class="mb-4 rounded-md border-gray-300 text-sm shadow-sm">
        <option value="">All</option>@foreach (['pending', 'approved', 'rejected', 'cancelled'] as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach
    </select>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Employee</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Dates</th><th class="px-4 py-3 text-right">Days</th><th class="px-4 py-3">Reason</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($requests as $r)
                    <tr wire:key="lr-{{ $r->id }}" class="align-top">
                        <td class="px-4 py-3">{{ $r->employee->employee_no }} {{ $r->employee->full_name }}</td>
                        <td class="px-4 py-3">{{ $r->leaveType->name }}</td>
                        <td class="px-4 py-3">{{ $r->from_date->format('Y-m-d') }}@if (! $r->from_date->isSameDay($r->to_date)) → {{ $r->to_date->format('Y-m-d') }}@endif</td>
                        <td class="px-4 py-3 text-right">{{ $r->days }}</td>
                        <td class="px-4 py-3">{{ $r->reason }}</td>
                        <td class="px-4 py-3">{{ ucfirst($r->status) }}@if ($r->decider)<div class="text-xs text-gray-500">by {{ $r->decider->name }}@if ($r->decision_note): {{ $r->decision_note }}@endif</div>@endif</td>
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
                                <button wire:click="cancel({{ $r->id }})" wire:confirm="Cancel this leave request?" class="text-gray-600 hover:underline">Cancel</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No leave requests.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</div>
