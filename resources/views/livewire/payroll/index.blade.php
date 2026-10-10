<div class="max-w-5xl">
    <x-page-header title="Payroll" />
    <x-flash />

    @if ($canProcess)
        <form wire:submit="create" class="border border-zinc-200 mb-6 flex flex-wrap items-end gap-3 rounded-xl bg-white p-6">
            <x-field label="Month to process" error="period"><x-text-input type="month" wire:model="period" class="mt-1 block" /></x-field>
            <x-primary-button>Start payroll run</x-primary-button>
        </form>
    @endif

    <div class="border border-zinc-200 overflow-x-auto rounded-xl bg-white">
        <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500"><tr><th class="px-4 py-3">Month</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Employees</th><th class="px-4 py-3 text-right">Net pay</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($runs as $run)
                    <tr wire:key="run-{{ $run->id }}">
                        <td class="px-4 py-3">{{ $run->label() }}</td>
                        <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs {{ $run->status === 'locked' ? 'bg-green-100 text-green-800' : ($run->status === 'draft' ? 'bg-zinc-100 text-zinc-700' : 'bg-amber-100 text-amber-800') }}">{{ ucfirst($run->status) }}</span></td>
                        <td class="px-4 py-3 text-right">{{ $run->payslips_count }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $run->payslips_sum_net_salary, 2) }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('payroll.show', $run) }}" wire:navigate class="text-indigo-600 hover:underline">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">No payroll runs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
