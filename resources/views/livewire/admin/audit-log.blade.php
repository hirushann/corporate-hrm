<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <x-page-header title="Audit log" />

    <div class="mb-4 grid gap-3 sm:grid-cols-4">
        <select wire:model.live="subject" class="rounded-md border-gray-300 text-sm shadow-sm">
            <option value="">All records</option>
            @foreach ($subjectTypes as $t)<option value="{{ $t }}">{{ class_basename($t) }}</option>@endforeach
        </select>
        <select wire:model.live="event" class="rounded-md border-gray-300 text-sm shadow-sm">
            <option value="">All events</option>
            @foreach (['created', 'updated', 'deleted'] as $e)<option value="{{ $e }}">{{ ucfirst($e) }}</option>@endforeach
        </select>
        <x-text-input type="date" wire:model.live="from" />
        <x-text-input type="date" wire:model.live="to" />
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">When</th><th class="px-4 py-3">Who</th><th class="px-4 py-3">Action</th><th class="px-4 py-3">Record</th><th class="px-4 py-3">Changes</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($activities as $a)
                    <tr wire:key="act-{{ $a->id }}" class="align-top">
                        <td class="whitespace-nowrap px-4 py-3">{{ $a->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">{{ $a->causer?->name ?? 'System' }}</td>
                        <td class="px-4 py-3">{{ $a->event ?? $a->description }}</td>
                        <td class="px-4 py-3">{{ $a->subject_type ? class_basename($a->subject_type).' #'.$a->subject_id : '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            @foreach (($a->attribute_changes['attributes'] ?? $a->properties['attributes'] ?? []) as $key => $new)
                                <div><span class="font-medium">{{ $key }}</span>:
                                    @if (isset(($a->attribute_changes['old'] ?? $a->properties['old'] ?? [])[$key]))<span class="text-gray-400 line-through">{{ is_scalar(($a->attribute_changes['old'] ?? $a->properties['old'])[$key]) ? ($a->attribute_changes['old'] ?? $a->properties['old'])[$key] : json_encode(($a->attribute_changes['old'] ?? $a->properties['old'])[$key]) }}</span> → @endif
                                    {{ is_scalar($new) ? $new : json_encode($new) }}</div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No activity recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $activities->links() }}</div>
</div>
