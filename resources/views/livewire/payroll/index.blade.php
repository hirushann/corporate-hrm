<div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
    <x-page-header title="Payroll" />
    <x-flash />

    @if ($canProcess)
        <form wire:submit="create" class="mb-6 flex flex-wrap items-end gap-3 rounded-lg bg-white p-6 shadow-sm">
            <x-field label="Month to process" error="period"><x-text-input type="month" wire:model="period" class="mt-1 block" /></x-field>
            <x-primary-button>Start payroll run</x-primary-button>
        </form>
    @endif

    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Month</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Employees</th><th class="px-4 py-3 text-right">Net pay</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($runs as $run)
                    <tr wire:key="run-{{ $run->id }}">
                        <td class="px-4 py-3">{{ $run->label() }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $run->status === 'locked' ? 'bg-green-100 text-green-800' : ($run->status === 'draft' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-800') }}">{{ ucfirst($run->status) }}</span></td>
                        <td class="px-4 py-3 text-right">{{ $run->payslips_count }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $run->payslips_sum_net_salary, 2) }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('payroll.show', $run) }}" wire:navigate class="text-indigo-600 hover:underline">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No payroll runs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
