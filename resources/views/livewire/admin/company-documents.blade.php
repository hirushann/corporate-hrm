<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6">
    <x-page-header title="Company documents" />
    <x-flash />

    <form wire:submit="upload" class="mb-6 grid gap-4 rounded-lg bg-white p-6 shadow-sm sm:grid-cols-2">
        <x-field label="Title" error="title"><x-text-input wire:model="title" class="mt-1 block w-full" /></x-field>
        <x-field label="Category" error="category"><select wire:model="category" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">@foreach ($categories as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></x-field>
        <x-field label="File (PDF, image, Word, Excel — max 10 MB)" error="file" class="sm:col-span-2"><input type="file" wire:model="file" class="mt-1 block w-full text-sm"></x-field>
        <div class="sm:col-span-2"><x-primary-button wire:loading.attr="disabled" wire:target="file">Upload</x-primary-button></div>
    </form>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Title</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Uploaded</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($documents as $d)
                    <tr wire:key="acd-{{ $d->id }}"><td class="px-4 py-3">{{ $d->title }} <span class="text-xs text-gray-400">({{ $d->original_name }})</span></td><td class="px-4 py-3">{{ $categories[$d->category] ?? $d->category }}</td><td class="px-4 py-3">{{ $d->created_at->format('Y-m-d') }}</td>
                        <td class="space-x-3 px-4 py-3 text-right"><a href="{{ route('documents.download', $d) }}" class="text-indigo-600 hover:underline">Download</a><button wire:click="delete({{ $d->id }})" wire:confirm="Delete this document?" class="text-red-600 hover:underline">Delete</button></td></tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No documents uploaded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
