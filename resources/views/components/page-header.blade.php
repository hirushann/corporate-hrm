@props(['title'])

<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-gray-800">{{ $title }}</h1>
    <div class="flex items-center gap-2">{{ $slot }}</div>
</div>
