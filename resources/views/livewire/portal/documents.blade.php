<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <x-page-header title="Company documents" />
    <div class="space-y-6">
        @forelse ($documents as $category => $items)
            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h2 class="mb-3 font-medium text-gray-900">{{ $categories[$category] ?? ucfirst($category) }}</h2>
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach ($items as $d)
                        <li wire:key="cd-{{ $d->id }}" class="flex items-center justify-between py-2"><span>{{ $d->title }} <span class="text-xs text-gray-400">{{ number_format($d->size / 1024) }} KB</span></span><a href="{{ route('documents.download', $d) }}" class="text-indigo-600 hover:underline">Download</a></li>
                    @endforeach
                </ul>
            </section>
        @empty
            <p class="rounded-lg bg-white p-8 text-center text-gray-500 shadow-sm">No documents have been shared yet.</p>
        @endforelse
    </div>
</div>
