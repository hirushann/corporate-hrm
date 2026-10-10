@props(['title'])

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <flux:heading size="xl" level="1">{{ $title }}</flux:heading>
    <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
</div>
