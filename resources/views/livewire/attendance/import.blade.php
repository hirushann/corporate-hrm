<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <x-page-header title="Import punches">
        <a href="{{ route('attendance.index') }}" wire:navigate class="text-sm text-gray-600 hover:underline">Back to attendance</a>
    </x-page-header>

    <form wire:submit="import" class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
        <p class="text-sm text-gray-600">
            Upload a CSV exported from the biometric device. The first row must be a header with an employee ID column
            (<code>employee_no</code>, <code>biometric_id</code>, <code>user_id</code>, <code>id</code> or <code>no</code>) and either one
            <code>datetime</code> column or separate <code>date</code> and <code>time</code> columns. Repeated punches are ignored, and attendance is recalculated for the affected dates.
        </p>
        <x-field label="CSV file (max 10 MB)" error="file"><input type="file" wire:model="file" accept=".csv,.txt" class="mt-1 block w-full text-sm"></x-field>
        <x-primary-button wire:loading.attr="disabled" wire:target="file,import">Import</x-primary-button>
    </form>

    @if ($result)
        <div class="mt-6 rounded-lg bg-white p-6 text-sm shadow-sm">
            <p><strong>{{ $result['imported'] }}</strong> punches imported, <strong>{{ $result['duplicates'] }}</strong> duplicates skipped, <strong>{{ $result['processed'] }}</strong> daily records recalculated.</p>
            @if ($result['error_count'])
                <p class="mt-3 font-medium text-red-700">{{ $result['error_count'] }} rows had problems:</p>
                <ul class="mt-1 list-disc pl-5 text-red-700">@foreach ($result['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul>
            @endif
        </div>
    @endif
</div>
