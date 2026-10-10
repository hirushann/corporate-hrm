<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <x-page-header title="Leave balances">
        @if ($canManage)<x-secondary-button wire:click="generate" wire:confirm="Create balances for every working employee for this year?">Generate balances</x-secondary-button>@endif
    </x-page-header>
    <x-flash />

    <div class="mb-4 grid gap-3 sm:grid-cols-4">
        <x-text-input type="number" wire:model.live="year" />
        <x-text-input wire:model.live.debounce.300ms="search" placeholder="Search name, code…" />
    </div>
    @error('year')<p class="mb-2 text-sm text-red-600">{{ $message }}</p>@enderror

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr><th class="px-4 py-3">Employee</th>@foreach ($types as $t)<th class="px-4 py-3 text-right">{{ $t->name }} <span class="normal-case text-gray-400">(left / allowed)</span></th>@endforeach</tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($employees as $e)
                    <tr wire:key="bal-{{ $e->id }}">
                        <td class="px-4 py-3"><span class="font-mono text-xs text-gray-500">{{ $e->employee_no }}</span> {{ $e->full_name }}</td>
                        @foreach ($types as $t)
                            @php $c = $grid[$e->id][$t->id]; @endphp
                            <td class="px-4 py-3 text-right">
                                @if ($c)
                                    {{ $c['allowed'] - $c['used'] - $c['pending'] }} / {{ $c['allowed'] }}
                                    @if ($c['pending'])<span class="text-xs text-amber-600">({{ $c['pending'] }} pending)</span>@endif
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $employees->links() }}</div>
</div>
