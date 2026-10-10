@props(['label', 'error' => null])

<div {{ $attributes }}>
    @if ($label !== '')<label class="block text-sm font-medium text-zinc-800">{{ $label }}</label>@endif
    {{ $slot }}
    @if ($error)
        @error($error)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    @endif
</div>
