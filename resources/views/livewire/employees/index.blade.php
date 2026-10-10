<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <x-page-header title="Employees">
        @if ($canCreate)
            <a href="{{ route('employees.create') }}" wire:navigate class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700">Add employee</a>
        @endif
    </x-page-header>
    <x-flash />

    <div class="mb-4 grid gap-3 sm:grid-cols-4">
        <x-text-input wire:model.live.debounce.300ms="search" placeholder="Search name, code, NIC…" class="w-full" />
        <select wire:model.live="department" class="rounded-md border-gray-300 text-sm shadow-sm">
            <option value="">All departments</option>
            @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
        </select>
        <select wire:model.live="branch" class="rounded-md border-gray-300 text-sm shadow-sm">
            <option value="">All branches</option>
            @foreach ($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
        </select>
        <select wire:model.live="status" class="rounded-md border-gray-300 text-sm shadow-sm">
            <option value="">All statuses</option>
            @foreach ($statuses as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Code</th><th class="px-4 py-3">Name</th><th class="px-4 py-3">Department</th>
                    <th class="px-4 py-3">Designation</th><th class="px-4 py-3">Branch</th><th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($employees as $e)
                    <tr wire:key="emp-{{ $e->id }}" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $e->employee_no }}</td>
                        <td class="px-4 py-3"><a href="{{ route('employees.show', $e) }}" wire:navigate class="font-medium text-indigo-600 hover:underline">{{ $e->full_name }}</a></td>
                        <td class="px-4 py-3">{{ $e->department->name }}</td>
                        <td class="px-4 py-3">{{ $e->designation->name }}</td>
                        <td class="px-4 py-3">{{ $e->branch->name }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs {{ in_array($e->status, ['active', 'probation']) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ ucfirst($e->status) }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No employees found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $employees->links() }}</div>
</div>
