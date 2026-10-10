<x-app-layout>
    <x-slot name="header">
        <flux:heading size="xl" level="1">{{ __('Account settings') }}</flux:heading>
    </x-slot>

    <div class="max-w-3xl space-y-6">
        <div class="rounded-xl border border-zinc-200 bg-white p-6">
            <div class="max-w-xl">
                <livewire:profile.update-profile-information-form />
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6">
            <div class="max-w-xl">
                <livewire:profile.update-password-form />
            </div>
        </div>
    </div>
</x-app-layout>
