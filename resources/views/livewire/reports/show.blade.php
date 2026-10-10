@php
    $cell = fn ($v) => is_float($v) ? number_format($v, 2) : $v;
    $numeric = fn ($v) => is_int($v) || is_float($v);
@endphp
<div class="max-w-7xl">
    <x-page-header :title="$report->title()">
        <a href="{{ route('reports.index') }}" wire:navigate class="text-sm text-zinc-600 hover:underline">All reports</a>
        @if ($canExport)
            @foreach (['xlsx' => 'Excel', 'pdf' => 'PDF', 'csv' => 'CSV'] as $format => $label)
                <flux:button size="sm" icon="arrow-down-tray" href="{{ route('reports.export', ['key' => $report::key(), 'format' => $format]).'?'.$query }}">{{ $label }}</flux:button>
            @endforeach
        @endif
    </x-page-header>
    <p class="-mt-4 mb-4 text-sm text-zinc-500">{{ $report->description() }}</p>

    @if ($report->filters())
        <div class="border border-zinc-200 mb-4 grid gap-3 rounded-xl bg-white p-4 sm:grid-cols-4">
            @foreach ($report->filters() as $filter)
                <x-field :label="$filter['label']">
                    @if ($filter['type'] === 'select')
                        <select wire:model.live="f.{{ $filter['name'] }}" class="mt-1 block w-full rounded-lg border-zinc-300 text-sm shadow-xs">
                            @unless ($filter['required'] ?? false)<option value="">All</option>@endunless
                            @foreach ($filter['options'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                        </select>
                    @else
                        <x-text-input type="{{ $filter['type'] }}" wire:model.live="f.{{ $filter['name'] }}" class="mt-1 block w-full" />
                    @endif
                </x-field>
            @endforeach
        </div>
    @endif

    <div class="border border-zinc-200 overflow-x-auto rounded-xl bg-white">
        <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500"><tr>@foreach ($report->headings() as $h)<th class="px-3 py-3">{{ $h }}</th>@endforeach</tr></thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($rows as $i => $row)
                    <tr wire:key="r-{{ $i }}">@foreach ($row as $v)<td class="px-3 py-2 {{ $numeric($v) ? 'text-right' : '' }} {{ $v === 'MISSING BANK DETAILS' ? 'font-medium text-red-600' : '' }}">{{ $cell($v) }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($report->headings()) }}" class="px-4 py-8 text-center text-zinc-500">No data for these filters.</td></tr>
                @endforelse
            </tbody>
            @if ($totals)
                <tfoot class="bg-zinc-50 font-semibold"><tr>@foreach ($totals as $v)<td class="px-3 py-2 {{ $numeric($v) ? 'text-right' : '' }}">{{ $cell($v) }}</td>@endforeach</tr></tfoot>
            @endif
        </table>
    </div>
    @if ($total > $limit)<p class="mt-2 text-xs text-zinc-500">Showing the first {{ $limit }} of {{ $total }} rows. Export to see all of them.</p>@endif
</div>
