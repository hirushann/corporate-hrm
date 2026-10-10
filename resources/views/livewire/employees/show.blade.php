<div class="max-w-5xl">
    <x-page-header :title="$employee->full_name">
        @if ($canUpdate)
            <flux:button variant="primary" size="sm" href="{{ route('employees.edit', $employee) }}" wire:navigate>Edit</flux:button>
        @endif
        @if ($canDelete)
            <x-danger-button wire:click="deleteEmployee" wire:confirm="Remove this employee? Their history is kept for audit.">Remove</x-danger-button>
        @endif
    </x-page-header>
    <x-flash />

    <p class="-mt-4 mb-6 text-sm text-zinc-500">{{ $employee->employee_no }} · {{ $employee->designation->name }} · {{ $employee->department->name }} · {{ ucfirst($employee->status) }}</p>

    <div class="mb-4 flex gap-6 border-b border-zinc-200 text-sm">
        @foreach (['profile' => 'Profile', 'salary' => 'Salary', 'documents' => 'Documents'] as $key => $label)
            @continue($key === 'salary' && ! $canSeeSalary)
            <button wire:click="setTab('{{ $key }}')" class="-mb-px border-b-2 px-1 pb-2 {{ $tab === $key ? 'border-indigo-500 font-medium text-zinc-900' : 'border-transparent text-zinc-500 hover:text-zinc-700' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'profile')
        @php
            $rows = [
                'Personal' => [
                    'NIC' => $employee->nic, 'Date of birth' => $employee->date_of_birth?->format('Y-m-d'), 'Gender' => ucfirst((string) $employee->gender),
                    'Marital status' => ucfirst((string) $employee->marital_status), 'Phone' => $employee->phone, 'Personal email' => $employee->personal_email,
                    'Address' => $employee->address, 'Emergency contact' => trim($employee->emergency_contact_name.' '.$employee->emergency_contact_phone),
                ],
                'Employment' => [
                    'Branch' => $employee->branch->name, 'Category' => $employee->category->name, 'Reports to' => $employee->manager?->full_name,
                    'Join date' => $employee->join_date->format('Y-m-d'), 'Confirmation date' => $employee->confirmation_date?->format('Y-m-d'),
                    'Resignation date' => $employee->resignation_date?->format('Y-m-d'),
                    'Login' => $employee->user ? $employee->user->email.' ('.$employee->user->roles->pluck('name')->implode(', ').')' : 'None',
                ],
            ];
            if ($canSeeSalary) {
                $rows['Bank & statutory'] = [
                    'Bank' => trim($employee->bank_name.' '.$employee->bank_branch), 'Account name' => $employee->bank_account_name,
                    'Account number' => $employee->bank_account_number ? str_repeat('•', max(strlen($employee->bank_account_number) - 4, 0)).substr($employee->bank_account_number, -4) : null,
                    'EPF no.' => $employee->epf_number, 'ETF no.' => $employee->etf_number, 'Tax TIN' => $employee->tax_tin ? '••••'.substr($employee->tax_tin, -3) : null,
                    'Applicable' => collect(['EPF' => $employee->epf_applicable, 'ETF' => $employee->etf_applicable, 'APIT' => $employee->apit_applicable])->filter()->keys()->implode(', ') ?: 'None',
                ];
            }
        @endphp
        <div class="space-y-6">
            @foreach ($rows as $heading => $items)
                <section class="border border-zinc-200 rounded-xl bg-white p-6">
                    <h2 class="mb-3 font-medium text-zinc-900">{{ $heading }}</h2>
                    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        @foreach ($items as $label => $value)
                            <div><dt class="text-zinc-500">{{ $label }}</dt><dd class="text-zinc-900">{{ filled($value) ? $value : '—' }}</dd></div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>
    @endif

    @if ($tab === 'salary' && $canSeeSalary)
        @if ($canUpdate)
            <form wire:submit="addSalary" class="border border-zinc-200 mb-6 grid gap-4 rounded-xl bg-white p-6 sm:grid-cols-4">
                <x-field label="Effective from" error="effectiveFrom"><x-text-input type="date" wire:model="effectiveFrom" class="mt-1 block w-full" /></x-field>
                <x-field label="Basic salary" error="basicSalary"><x-text-input type="number" step="0.01" wire:model="basicSalary" class="mt-1 block w-full" /></x-field>
                <x-field label="Fixed allowances" error="fixedAllowances"><x-text-input type="number" step="0.01" wire:model="fixedAllowances" class="mt-1 block w-full" /></x-field>
                <x-field label="Reason" error="salaryReason"><x-text-input wire:model="salaryReason" class="mt-1 block w-full" /></x-field>
                <div class="sm:col-span-4"><x-primary-button>Add revision</x-primary-button></div>
            </form>
        @endif
        <div class="border border-zinc-200 overflow-x-auto rounded-xl bg-white">
            <table class="min-w-full divide-y divide-zinc-200 text-sm">
                <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500"><tr><th class="px-4 py-3">Effective from</th><th class="px-4 py-3 text-right">Basic</th><th class="px-4 py-3 text-right">Allowances</th><th class="px-4 py-3">Reason</th></tr></thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($salaries as $s)
                        <tr wire:key="sal-{{ $s->id }}"><td class="px-4 py-3">{{ $s->effective_from->format('Y-m-d') }}</td><td class="px-4 py-3 text-right">{{ number_format($s->basic_salary, 2) }}</td><td class="px-4 py-3 text-right">{{ number_format($s->fixed_allowances, 2) }}</td><td class="px-4 py-3">{{ $s->reason }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500">No salary recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h2 class="mb-2 mt-8 font-medium text-zinc-900">Recurring allowances &amp; deductions</h2>
        @if ($canUpdate)
            <form wire:submit="addComponent" class="border border-zinc-200 mb-4 grid gap-4 rounded-xl bg-white p-6 sm:grid-cols-5">
                <x-field label="Component" error="componentId"><select wire:model="componentId" class="mt-1 block w-full rounded-lg border-zinc-300 text-sm shadow-xs"><option value="">Select…</option>@foreach ($componentOptions as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->type }})</option>@endforeach</select></x-field>
                <x-field label="Monthly amount" error="componentAmount"><x-text-input type="number" step="0.01" wire:model="componentAmount" class="mt-1 block w-full" /></x-field>
                <x-field label="From" error="componentFrom"><x-text-input type="date" wire:model="componentFrom" class="mt-1 block w-full" /></x-field>
                <x-field label="To (optional)" error="componentTo"><x-text-input type="date" wire:model="componentTo" class="mt-1 block w-full" /></x-field>
                <div class="flex items-end"><x-primary-button>Add</x-primary-button></div>
            </form>
        @endif
        <div class="border border-zinc-200 overflow-x-auto rounded-xl bg-white">
            <table class="min-w-full divide-y divide-zinc-200 text-sm">
                <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500"><tr><th class="px-4 py-3">Component</th><th class="px-4 py-3">Type</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3">Period</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($components as $c)
                        <tr wire:key="ec-{{ $c->id }}"><td class="px-4 py-3">{{ $c->component->name }}</td><td class="px-4 py-3">{{ ucfirst($c->component->type) }}</td><td class="px-4 py-3 text-right">{{ number_format($c->amount, 2) }}</td><td class="px-4 py-3">{{ $c->effective_from->format('Y-m-d') }} → {{ $c->effective_to?->format('Y-m-d') ?? 'ongoing' }}</td><td class="px-4 py-3 text-right">@if ($canUpdate)<button wire:click="removeComponent({{ $c->id }})" wire:confirm="Remove this component?" class="text-red-600 hover:underline">Remove</button>@endif</td></tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-zinc-500">None assigned.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    @if ($tab === 'documents')
        @if ($canManageDocs)
            <form wire:submit="uploadDocument" class="border border-zinc-200 mb-6 grid gap-4 rounded-xl bg-white p-6 sm:grid-cols-2">
                <x-field label="Type" error="docType"><select wire:model="docType" class="mt-1 block w-full rounded-lg border-zinc-300 text-sm shadow-xs">@foreach ($docTypes as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></x-field>
                <x-field label="Title" error="docTitle"><x-text-input wire:model="docTitle" class="mt-1 block w-full" /></x-field>
                <x-field label="Expires on" error="docExpiresOn"><x-text-input type="date" wire:model="docExpiresOn" class="mt-1 block w-full" /></x-field>
                <x-field label="File (PDF, image, Word — max 5 MB)" error="docFile"><input type="file" wire:model="docFile" class="mt-1 block w-full text-sm"></x-field>
                <div class="sm:col-span-2"><x-primary-button wire:loading.attr="disabled" wire:target="docFile">Upload</x-primary-button></div>
            </form>
        @endif
        <div class="border border-zinc-200 overflow-x-auto rounded-xl bg-white">
            <table class="min-w-full divide-y divide-zinc-200 text-sm">
                <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500"><tr><th class="px-4 py-3">Title</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Expires</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse ($documents as $d)
                        <tr wire:key="doc-{{ $d->id }}">
                            <td class="px-4 py-3">{{ $d->title }} <span class="text-xs text-zinc-400">({{ $d->original_name }})</span></td>
                            <td class="px-4 py-3">{{ $docTypes[$d->type] ?? $d->type }}</td>
                            <td class="px-4 py-3 {{ $d->expires_on?->isPast() ? 'text-red-600' : '' }}">{{ $d->expires_on?->format('Y-m-d') ?? '—' }}</td>
                            <td class="space-x-3 px-4 py-3 text-right">
                                <a href="{{ route('employees.documents.download', $d) }}" class="text-indigo-600 hover:underline">Download</a>
                                @if ($canManageDocs)<button wire:click="deleteDocument({{ $d->id }})" wire:confirm="Delete this document?" class="text-red-600 hover:underline">Delete</button>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500">No documents uploaded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
