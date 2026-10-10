<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
    <x-page-header title="Attendance adjustments" />
    <x-flash />

    <form wire:submit="submit" class="mb-6 grid gap-4 rounded-lg bg-white p-6 shadow-sm sm:grid-cols-4">
        <h2 class="font-medium text-gray-900 sm:col-span-4">Request an adjustment</h2>
        <x-field label="Employee" error="employeeId" class="sm:col-span-2"><select wire:model="employeeId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"><option value="">Select…</option>@foreach ($employees as $e)<option value="{{ $e->id }}">{{ $e->employee_no }} — {{ $e->full_name }}</option>@endforeach</select></x-field>
        <x-field label="Date" error="date"><x-text-input type="date" wire:model="date" class="mt-1 block w-full" /></x-field>
        <div class="grid grid-cols-2 gap-2">
            <x-field label="In" error="inTime"><x-text-input type="time" wire:model="inTime" class="mt-1 block w-full" /></x-field>
            <x-field label="Out" error="outTime"><x-text-input type="time" wire:model="outTime" class="mt-1 block w-full" /></x-field>
        </div>
        <x-field label="Reason" error="reason" class="sm:col-span-3"><x-text-input wire:model="reason" class="mt-1 block w-full" /></x-field>
        <div class="flex items-end"><x-primary-button>Submit</x-primary-button></div>
    </form>

    <select wire:model.live="statusFilter" class="mb-4 rounded-md border-gray-300 text-sm shadow-sm">
        <option value="">All</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option>
    </select>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Employee</th><th class="px-4 py-3">Date</th><th class="px-4 py-3">In / Out</th><th class="px-4 py-3">Reason</th><th class="px-4 py-3">Requested by</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($adjustments as $a)
                    <tr wire:key="adj-{{ $a->id }}" class="align-top">
                        <td class="px-4 py-3">{{ $a->employee->employee_no }} {{ $a->employee->full_name }}</td>
                        <td class="px-4 py-3">{{ $a->date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $a->in_at?->format('H:i') ?? '—' }} / {{ $a->out_at?->format('H:i') ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $a->reason }}</td>
                        <td class="px-4 py-3">{{ $a->requester->name }}</td>
                        <td class="px-4 py-3">{{ ucfirst($a->status) }}@if ($a->reviewer)<div class="text-xs text-gray-500">by {{ $a->reviewer->name }}@if ($a->review_note): {{ $a->review_note }}@endif</div>@endif</td>
                        <td class="px-4 py-3 text-right">
                            @if ($canReview && $a->status === 'pending' && $a->requested_by !== auth()->id())
                                @if ($reviewingId === $a->id)
                                    <x-text-input wire:model="reviewNote" placeholder="Note (optional)" class="mb-2 w-full" />
                                    <button wire:click="review({{ $a->id }}, true)" class="text-green-700 hover:underline">Approve</button>
                                    <button wire:click="review({{ $a->id }}, false)" class="ms-3 text-red-700 hover:underline">Reject</button>
                                @else
                                    <button wire:click="$set('reviewingId', {{ $a->id }})" class="text-indigo-600 hover:underline">Review</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">No adjustments.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $adjustments->links() }}</div>
</div>
