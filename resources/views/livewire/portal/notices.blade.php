<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <x-page-header title="Notices" />
    <div class="space-y-4">
        @forelse ($notices as $n)
            <article wire:key="n-{{ $n->id }}" class="rounded-lg bg-white p-6 shadow-sm">
                <h2 class="font-medium text-gray-900">{{ $n->is_pinned ? '📌 ' : '' }}{{ $n->title }}</h2>
                <p class="mb-3 text-xs text-gray-400">{{ $n->published_at->format('Y-m-d') }}{{ $n->department ? ' · '.$n->department->name : '' }}</p>
                <div class="whitespace-pre-line text-sm text-gray-700">{{ $n->body }}</div>
            </article>
        @empty
            <p class="rounded-lg bg-white p-8 text-center text-gray-500 shadow-sm">No notices at the moment.</p>
        @endforelse
    </div>
    <div class="mt-4">{{ $notices->links() }}</div>
</div>
