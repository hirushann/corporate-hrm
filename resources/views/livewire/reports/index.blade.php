<div class="max-w-5xl">
    <x-page-header title="Reports" />
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($reports as $key => $report)
            <a href="{{ route('reports.show', $key) }}" wire:navigate class="border border-zinc-200 rounded-xl bg-white p-5 hover:bg-zinc-50">
                <h2 class="font-medium text-zinc-900">{{ $report->title() }}</h2>
                <p class="mt-1 text-sm text-zinc-500">{{ $report->description() }}</p>
            </a>
        @endforeach
    </div>
</div>
