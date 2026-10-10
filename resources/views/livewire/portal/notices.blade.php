<div class="max-w-3xl">
    <x-page-header title="Notices" />
    <div class="space-y-4">
        @forelse ($notices as $n)
            <article wire:key="n-{{ $n->id }}" class="border border-zinc-200 rounded-xl bg-white p-6">
                <h2 class="font-medium text-zinc-900">{{ $n->is_pinned ? '📌 ' : '' }}{{ $n->title }}</h2>
                <p class="mb-3 text-xs text-zinc-400">{{ $n->published_at->format('Y-m-d') }}{{ $n->department ? ' · '.$n->department->name : '' }}</p>
                <div class="whitespace-pre-line text-sm text-zinc-700">{{ $n->body }}</div>
            </article>
        @empty
            <p class="border border-zinc-200 rounded-xl bg-white p-8 text-center text-zinc-500">No notices at the moment.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $notices->links() }}</div>
</div>
