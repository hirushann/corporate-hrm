<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <x-page-header title="My leave" />
    <x-flash />

    <div class="mb-6 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
        @foreach ($balances as $b)
            <div class="rounded-lg bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">{{ $b['name'] }} ({{ $year }})</div><div class="text-lg font-semibold">{{ $b['left'] === null ? 'Unlimited' : $b['left'].' left' }}</div><div class="text-xs text-gray-400">{{ $b['taken'] }} taken</div></div>
        @endforeach
    </div>

    <form wire:submit="submit" class="mb-6 grid gap-4 rounded-lg bg-white p-6 shadow-sm sm:grid-cols-4">
        <h2 class="font-medium text-gray-900 sm:col-span-4">Request leave</h2>
        <x-field label="Leave type" error="leaveTypeId"><select wire:model="leaveTypeId" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm"><option value="">Select…</option>@foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></x-field>
        <x-field label="From" error="fromDate"><x-text-input type="date" wire:model="fromDate" class="mt-1 block w-full" /></x-field>
        <x-field label="To" error="toDate"><x-text-input type="date" wire:model="toDate" class="mt-1 block w-full" /></x-field>
        <label class="flex items-end gap-2 pb-2 text-sm"><input type="checkbox" wire:model="halfDay" class="rounded border-gray-300"> Half day</label>
        <x-field label="Reason" error="reason" class="sm:col-span-3"><x-text-input wire:model="reason" class="mt-1 block w-full" /></x-field>
        <div class="flex items-end"><x-primary-button>Submit request</x-primary-button></div>
        @error('leave')<p class="text-sm text-red-600 sm:col-span-4">{{ $message }}</p>@enderror
    </form>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Type</th><th class="px-4 py-3">Dates</th><th class="px-4 py-3 text-right">Days</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($requests as $r)
                    <tr wire:key="ml-{{ $r->id }}">
                        <td class="px-4 py-3">{{ $r->leaveType->name }}</td>
                        <td class="px-4 py-3">{{ $r->from_date->format('Y-m-d') }}@if (! $r->from_date->isSameDay($r->to_date)) → {{ $r->to_date->format('Y-m-d') }}@endif</td>
                        <td class="px-4 py-3 text-right">{{ $r->days }}</td>
                        <td class="px-4 py-3">{{ ucfirst($r->status) }}@if ($r->decision_note)<div class="text-xs text-gray-500">{{ $r->decision_note }}</div>@endif</td>
                        <td class="px-4 py-3 text-right">@if ($r->status === 'pending')<button wire:click="cancel({{ $r->id }})" wire:confirm="Cancel this request?" class="text-red-600 hover:underline">Cancel</button>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">You have not requested any leave yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
