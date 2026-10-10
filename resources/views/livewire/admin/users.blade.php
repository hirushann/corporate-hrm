<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <x-page-header title="Users">
        @if ($canManage)<x-primary-button wire:click="create">Add user</x-primary-button>@endif
    </x-page-header>
    <x-flash />

    <x-text-input wire:model.live.debounce.300ms="search" placeholder="Search name or email…" class="mb-4 w-full sm:w-72" />

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Employee</th><th class="px-4 py-3">Status</th>@if ($canManage)<th class="px-4 py-3"></th>@endif</tr></thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($users as $u)
                    <tr wire:key="user-{{ $u->id }}">
                        <td class="px-4 py-3">{{ $u->name }}</td>
                        <td class="px-4 py-3">{{ $u->email }}</td>
                        <td class="px-4 py-3">{{ $u->roles->pluck('name')->implode(', ') }}</td>
                        <td class="px-4 py-3">{{ $u->employee?->employee_no ?? '—' }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $u->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $u->is_active ? 'Active' : 'Disabled' }}</span></td>
                        @if ($canManage)
                            <td class="space-x-3 px-4 py-3 text-right">
                                <button wire:click="edit({{ $u->id }})" class="text-indigo-600 hover:underline">Edit</button>
                                <button wire:click="sendResetLink({{ $u->id }})" class="text-gray-600 hover:underline">Reset password</button>
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-500/50 p-4">
            <form wire:submit="save" class="w-full max-w-md space-y-4 rounded-lg bg-white p-6 shadow-xl">
                <h2 class="text-lg font-medium text-gray-900">{{ $editingId ? 'Edit user' : 'Add user' }}</h2>
                <x-field label="Name" error="name"><x-text-input wire:model="name" class="mt-1 block w-full" /></x-field>
                <x-field label="Email" error="email"><x-text-input type="email" wire:model="email" class="mt-1 block w-full" /></x-field>
                <x-field label="Role" error="role"><select wire:model="role" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">@foreach ($roles as $r)<option>{{ $r }}</option>@endforeach</select></x-field>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded border-gray-300"> Active (can sign in)</label>
                @unless ($editingId)<p class="text-xs text-gray-500">A password-setup link is emailed to the new user.</p>@endunless
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" wire:click="$set('showForm', false)">Cancel</x-secondary-button>
                    <x-primary-button>Save</x-primary-button>
                </div>
            </form>
        </div>
    @endif
</div>
