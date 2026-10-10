@if (session('status'))
    <flux:callout variant="success" icon="check-circle" class="mb-4" :heading="session('status')" />
@endif
