<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <x-page-header title="My payslips" />
    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Month</th><th class="px-4 py-3 text-right">Gross</th><th class="px-4 py-3 text-right">Deductions</th><th class="px-4 py-3 text-right">Net pay</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($payslips as $p)
                    <tr wire:key="mp-{{ $p->id }}">
                        <td class="px-4 py-3">{{ $p->run->label() }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($p->gross, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format($p->total_deductions, 2) }}</td>
                        <td class="px-4 py-3 text-right font-medium">{{ number_format($p->net_salary, 2) }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('payslips.pdf', $p) }}" class="text-indigo-600 hover:underline">Download PDF</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No payslips have been published yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
