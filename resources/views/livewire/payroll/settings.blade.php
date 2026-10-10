<div class="max-w-3xl">
    <x-page-header title="Payroll settings" />
    <x-flash />

    <form wire:submit="save" class="border border-zinc-200 grid gap-4 rounded-xl bg-white p-6 sm:grid-cols-2">
        @foreach ($fields as $key => $label)
            <x-field :label="$label" :error="'s.'.$key"><x-text-input type="number" step="0.01" wire:model="s.{{ $key }}" class="mt-1 block w-full" :disabled="! $canManage" /></x-field>
        @endforeach
        <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" wire:model="s.epf_includes_allowances" class="rounded border-zinc-300" @disabled(! $canManage)> Include EPF-applicable allowances in the EPF/ETF base (otherwise basic less no-pay only)</label>
        @if ($canManage)<div class="sm:col-span-2"><x-primary-button>Save</x-primary-button></div>@endif
    </form>
</div>
