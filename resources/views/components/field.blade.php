@props(['label', 'error' => null])

<div {{ $attributes }}>
    <label class="block text-sm font-medium text-gray-700">{{ $label }}</label>
    {{ $slot }}
    @if ($error)
        @error($error)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    @endif
</div>
