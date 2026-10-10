<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <x-page-header :title="$heading">
        @if ($canManage)
            <x-primary-button wire:click="create">Add new</x-primary-button>
        @endif
    </x-page-header>
    <x-flash />

    <x-text-input wire:model.live.debounce.300ms="search" placeholder="Search by name…" class="mb-4 w-full sm:w-72" />

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    @foreach ($fields as $key => $field)
                        <th class="px-4 py-3">{{ $field['label'] }}</th>
                    @endforeach
                    @if ($canManage)<th class="px-4 py-3"></th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $row)
                    <tr wire:key="row-{{ $row->id }}">
                        @foreach ($fields as $key => $field)
                            <td class="px-4 py-3">
                                @if (($field['type'] ?? 'text') === 'weekdays')
                                    {{ collect((array) $row->{$key})->map(fn ($d) => ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][(int) $d])->implode(', ') }}
                                @elseif (($field['type'] ?? 'text') === 'select')
                                    {{ $field['options'][$row->{$key}] ?? $row->{$key} }}
                                @elseif (($field['type'] ?? 'text') === 'time')
                                    {{ substr((string) $row->{$key}, 0, 5) }}
                                @elseif (($field['type'] ?? 'text') === 'date')
                                    {{ $row->{$key}?->format('Y-m-d') }}
                                @elseif (($field['type'] ?? 'text') === 'checkbox')
                                    <span class="rounded-full px-2 py-0.5 text-xs {{ $row->{$key} ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $row->{$key} ? 'Active' : 'Inactive' }}</span>
                                @else
                                    {{ $row->{$key} }}
                                @endif
                            </td>
                        @endforeach
                        @if ($canManage)
                            <td class="space-x-3 px-4 py-3 text-right">
                                <button wire:click="edit({{ $row->id }})" class="text-indigo-600 hover:underline">Edit</button>
                                @if ($canToggle)<button wire:click="toggleActive({{ $row->id }})" class="text-gray-600 hover:underline">{{ $row->is_active ? 'Deactivate' : 'Activate' }}</button>@endif
                                @unless ($canToggle)<button wire:click="delete({{ $row->id }})" wire:confirm="Delete this entry?" class="text-red-600 hover:underline">Delete</button>@endunless
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ count($fields) + 1 }}" class="px-4 py-8 text-center text-gray-500">Nothing here yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4">
            <form wire:submit="save" class="w-full max-w-md space-y-4 rounded-lg bg-white p-6 shadow-xl">
                <h2 class="text-lg font-medium text-gray-900">{{ $editingId ? 'Edit' : 'Add' }} — {{ $heading }}</h2>
                @foreach ($fields as $key => $field)
                    @if (($field['type'] ?? 'text') === 'checkbox')
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="form.{{ $key }}" class="rounded border-gray-300"> {{ $field['label'] }}</label>
                    @elseif (($field['type'] ?? 'text') === 'weekdays')
                        <x-field :label="$field['label']" :error="'form.'.$key">
                            <div class="mt-1 flex flex-wrap gap-3 text-sm">
                                @foreach ([1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'] as $n => $label)
                                    <label class="flex items-center gap-1"><input type="checkbox" value="{{ $n }}" wire:model="form.{{ $key }}" class="rounded border-gray-300"> {{ $label }}</label>
                                @endforeach
                            </div>
                        </x-field>
                    @elseif (($field['type'] ?? 'text') === 'select')
                        <x-field :label="$field['label']" :error="'form.'.$key">
                            <select wire:model="form.{{ $key }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                @foreach ($field['options'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                        </x-field>
                    @else
                        <x-field :label="$field['label']" :error="'form.'.$key">
                            <x-text-input type="{{ in_array($field['type'] ?? 'text', ['time', 'date', 'number']) ? $field['type'] : 'text' }}" step="{{ ($field['type'] ?? '') === 'number' ? '0.5' : '' }}" wire:model="form.{{ $key }}" class="mt-1 block w-full" />
                        </x-field>
                    @endif
                @endforeach
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" wire:click="$set('showForm', false)">Cancel</x-secondary-button>
                    <x-primary-button>Save</x-primary-button>
                </div>
            </form>
        </div>
    @endif
</div>
