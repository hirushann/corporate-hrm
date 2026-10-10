<div class="max-w-3xl">
    <x-page-header title="Company profile" />
    <x-flash />

    <form wire:submit="save" class="border border-zinc-200 grid gap-4 rounded-xl bg-white p-6 sm:grid-cols-2">
        <x-field label="Company name" error="name" class="sm:col-span-2">
            <x-text-input wire:model="name" class="mt-1 block w-full" :disabled="! $canManage" />
        </x-field>
        <x-field label="Registration no." error="registration_no"><x-text-input wire:model="registration_no" class="mt-1 block w-full" :disabled="! $canManage" /></x-field>
        <x-field label="Phone" error="phone"><x-text-input wire:model="phone" class="mt-1 block w-full" :disabled="! $canManage" /></x-field>
        <x-field label="Email" error="email"><x-text-input wire:model="email" class="mt-1 block w-full" :disabled="! $canManage" /></x-field>
        <x-field label="Address" error="address"><x-text-input wire:model="address" class="mt-1 block w-full" :disabled="! $canManage" /></x-field>
        <x-field label="EPF registration no." error="epf_registration_no"><x-text-input wire:model="epf_registration_no" class="mt-1 block w-full" :disabled="! $canManage" /></x-field>
        <x-field label="ETF registration no." error="etf_registration_no"><x-text-input wire:model="etf_registration_no" class="mt-1 block w-full" :disabled="! $canManage" /></x-field>
        <x-field label="Tax registration no." error="tax_registration_no"><x-text-input wire:model="tax_registration_no" class="mt-1 block w-full" :disabled="! $canManage" /></x-field>

        @if ($canManage)
            <div class="sm:col-span-2"><x-primary-button>Save</x-primary-button></div>
        @endif
    </form>
</div>
