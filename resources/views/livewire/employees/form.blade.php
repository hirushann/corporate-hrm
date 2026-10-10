@php
    $input = 'mt-1 block w-full rounded-lg border-zinc-300 text-sm shadow-xs';
@endphp
<div class="max-w-5xl">
    <x-page-header :title="$employee ? 'Edit '.$employee->full_name : 'Add employee'">
        <a href="{{ $employee ? route('employees.show', $employee) : route('employees.index') }}" wire:navigate class="text-sm text-zinc-600 hover:underline">Cancel</a>
    </x-page-header>

    <form wire:submit="save" class="space-y-6">
        <section class="border border-zinc-200 grid gap-4 rounded-xl bg-white p-6 sm:grid-cols-2">
            <h2 class="font-medium text-zinc-900 sm:col-span-2">Personal details</h2>
            <x-field label="Full name *" error="f.full_name"><x-text-input wire:model="f.full_name" class="mt-1 block w-full" /></x-field>
            <x-field label="Biometric device ID" error="f.biometric_id"><x-text-input wire:model="f.biometric_id" placeholder="Defaults to the employee code" class="mt-1 block w-full" /></x-field>
            <x-field label="NIC" error="f.nic"><x-text-input wire:model="f.nic" class="mt-1 block w-full" /></x-field>
            <x-field label="Date of birth" error="f.date_of_birth"><x-text-input type="date" wire:model="f.date_of_birth" class="mt-1 block w-full" /></x-field>
            <x-field label="Gender" error="f.gender">
                <select wire:model="f.gender" class="{{ $input }}"><option value="">—</option><option value="male">Male</option><option value="female">Female</option><option value="other">Other</option></select>
            </x-field>
            <x-field label="Marital status" error="f.marital_status">
                <select wire:model="f.marital_status" class="{{ $input }}"><option value="">—</option>@foreach (['single', 'married', 'divorced', 'widowed'] as $m)<option value="{{ $m }}">{{ ucfirst($m) }}</option>@endforeach</select>
            </x-field>
            <x-field label="Phone" error="f.phone"><x-text-input wire:model="f.phone" class="mt-1 block w-full" /></x-field>
            <x-field label="Personal email" error="f.personal_email"><x-text-input wire:model="f.personal_email" class="mt-1 block w-full" /></x-field>
            <x-field label="Address" error="f.address"><x-text-input wire:model="f.address" class="mt-1 block w-full" /></x-field>
            <x-field label="Emergency contact name" error="f.emergency_contact_name"><x-text-input wire:model="f.emergency_contact_name" class="mt-1 block w-full" /></x-field>
            <x-field label="Emergency contact phone" error="f.emergency_contact_phone"><x-text-input wire:model="f.emergency_contact_phone" class="mt-1 block w-full" /></x-field>
        </section>

        <section class="border border-zinc-200 grid gap-4 rounded-xl bg-white p-6 sm:grid-cols-2">
            <h2 class="font-medium text-zinc-900 sm:col-span-2">Employment</h2>
            <x-field label="Branch *" error="f.branch_id"><select wire:model="f.branch_id" class="{{ $input }}"><option value="">Select…</option>@foreach ($branches as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select></x-field>
            <x-field label="Department *" error="f.department_id"><select wire:model="f.department_id" class="{{ $input }}"><option value="">Select…</option>@foreach ($departments as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select></x-field>
            <x-field label="Designation *" error="f.designation_id"><select wire:model="f.designation_id" class="{{ $input }}"><option value="">Select…</option>@foreach ($designations as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select></x-field>
            <x-field label="Category *" error="f.employee_category_id"><select wire:model="f.employee_category_id" class="{{ $input }}"><option value="">Select…</option>@foreach ($categories as $o)<option value="{{ $o->id }}">{{ $o->name }}</option>@endforeach</select></x-field>
            <x-field label="Reports to" error="f.manager_id"><select wire:model="f.manager_id" class="{{ $input }}"><option value="">—</option>@foreach ($managers as $o)<option value="{{ $o->id }}">{{ $o->employee_no }} — {{ $o->full_name }}</option>@endforeach</select></x-field>
            <x-field label="Status *" error="f.status"><select wire:model="f.status" class="{{ $input }}">@foreach ($statuses as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach</select></x-field>
            <x-field label="Join date *" error="f.join_date"><x-text-input type="date" wire:model="f.join_date" class="mt-1 block w-full" /></x-field>
            <x-field label="Confirmation date" error="f.confirmation_date"><x-text-input type="date" wire:model="f.confirmation_date" class="mt-1 block w-full" /></x-field>
            <x-field label="Resignation date" error="f.resignation_date"><x-text-input type="date" wire:model="f.resignation_date" class="mt-1 block w-full" /></x-field>
        </section>

        @if ($canSeeSalary)
            <section class="border border-zinc-200 grid gap-4 rounded-xl bg-white p-6 sm:grid-cols-2">
                <h2 class="font-medium text-zinc-900 sm:col-span-2">Bank &amp; statutory</h2>
                <x-field label="Bank" error="f.bank_name"><x-text-input wire:model="f.bank_name" class="mt-1 block w-full" /></x-field>
                <x-field label="Bank branch" error="f.bank_branch"><x-text-input wire:model="f.bank_branch" class="mt-1 block w-full" /></x-field>
                <x-field label="Account number" error="f.bank_account_number"><x-text-input wire:model="f.bank_account_number" class="mt-1 block w-full" /></x-field>
                <x-field label="Account name" error="f.bank_account_name"><x-text-input wire:model="f.bank_account_name" class="mt-1 block w-full" /></x-field>
                <x-field label="EPF number" error="f.epf_number"><x-text-input wire:model="f.epf_number" class="mt-1 block w-full" /></x-field>
                <x-field label="ETF number" error="f.etf_number"><x-text-input wire:model="f.etf_number" class="mt-1 block w-full" /></x-field>
                <x-field label="Tax TIN" error="f.tax_tin"><x-text-input wire:model="f.tax_tin" class="mt-1 block w-full" /></x-field>
                <div class="flex flex-wrap items-end gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="f.epf_applicable" class="rounded border-zinc-300"> EPF applicable</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="f.etf_applicable" class="rounded border-zinc-300"> ETF applicable</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="f.apit_applicable" class="rounded border-zinc-300"> APIT applicable</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="f.ot_eligible" class="rounded border-zinc-300"> Overtime eligible</label>
                </div>
            </section>

            @unless ($employee)
                <section class="border border-zinc-200 grid gap-4 rounded-xl bg-white p-6 sm:grid-cols-2">
                    <h2 class="font-medium text-zinc-900 sm:col-span-2">Opening salary <span class="text-sm font-normal text-zinc-500">(effective from the join date)</span></h2>
                    <x-field label="Basic salary" error="basicSalary"><x-text-input type="number" step="0.01" wire:model="basicSalary" class="mt-1 block w-full" /></x-field>
                    <x-field label="Fixed allowances" error="fixedAllowances"><x-text-input type="number" step="0.01" wire:model="fixedAllowances" class="mt-1 block w-full" /></x-field>
                </section>
            @endunless
        @endif

        @if (! $employee && $canCreateLogin)
            <section class="border border-zinc-200 grid gap-4 rounded-xl bg-white p-6 sm:grid-cols-2">
                <label class="flex items-center gap-2 text-sm font-medium text-zinc-900 sm:col-span-2"><input type="checkbox" wire:model.live="createLogin" class="rounded border-zinc-300"> Create a login for this employee</label>
                @if ($createLogin)
                    <x-field label="Login email" error="loginEmail"><x-text-input type="email" wire:model="loginEmail" class="mt-1 block w-full" /></x-field>
                    <x-field label="Role" error="loginRole"><select wire:model="loginRole" class="{{ $input }}">@foreach ($roles as $r)<option>{{ $r }}</option>@endforeach</select></x-field>
                    <p class="text-xs text-zinc-500 sm:col-span-2">A password-setup link is emailed to this address.</p>
                @endif
            </section>
        @endif

        <div class="flex justify-end"><x-primary-button>Save employee</x-primary-button></div>
    </form>
</div>
