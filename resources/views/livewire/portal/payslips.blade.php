<div class="max-w-3xl">
    <x-page-header title="My payslips" />
    <div class="border border-zinc-200 overflow-x-auto rounded-xl bg-white">
        <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500"><tr><th class="px-4 py-3">Month</th><th class="px-4 py-3 text-right">Gross</th><th class="px-4 py-3 text-right">Deductions</th><th class="px-4 py-3 text-right">Net pay</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($payslips as $p)
                    <tr wire:key="mp-{{ $p->id }}">
                        <td class="px-4 py-3">{{ $p->run->label() }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($p->gross, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($p->total_deductions, 2) }}</td>
                        <td class="px-4 py-3 text-right font-medium">{{ number_format($p->net_salary, 2) }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('payslips.pdf', $p) }}" class="text-indigo-600 hover:underline">Download PDF</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">No payslips have been published yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
