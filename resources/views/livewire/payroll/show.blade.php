@php $money = fn ($v) => number_format((float) $v, 2); @endphp
<div class="max-w-7xl">
    <x-page-header :title="'Payroll — '.$run->label()">
        <a href="{{ route('payroll.index') }}" wire:navigate class="text-sm text-zinc-600 hover:underline">All runs</a>
        @can('payroll.view')<flux:button size="sm" icon="arrow-down-tray" href="{{ route('payroll.export', $run) }}">Salary sheet (Excel)</flux:button>@endcan
    </x-page-header>
    <x-flash />

    <div class="border border-zinc-200 mb-4 flex flex-wrap items-center gap-3 rounded-xl bg-white p-4 text-sm">
        <span class="rounded-full px-2 py-0.5 text-xs {{ $run->status === 'locked' ? 'bg-green-100 text-green-800' : ($draft ? 'bg-zinc-100 text-zinc-700' : 'bg-amber-100 text-amber-800') }}">{{ ucfirst($run->status) }}</span>
        <span class="text-zinc-500">Prepared by {{ $run->preparer->name }}@if ($run->submitter), submitted by {{ $run->submitter->name }}@endif @if ($run->approver), approved by {{ $run->approver->name }}@endif @if ($run->locker), locked by {{ $run->locker->name }}@endif</span>
        @if ($run->review_note)<span class="text-red-700">Returned: {{ $run->review_note }}</span>@endif

        <div class="ms-auto flex flex-wrap items-center gap-2">
            @if ($draft && $canProcess)
                <x-secondary-button wire:click="recalculate" wire:loading.attr="disabled">Recalculate</x-secondary-button>
                <x-primary-button wire:click="submit" wire:confirm="Submit this payroll for approval?">Submit for approval</x-primary-button>
                <x-danger-button wire:click="deleteDraft" wire:confirm="Delete this draft payroll run?">Delete</x-danger-button>
            @endif
            @if (in_array($run->status, ['submitted', 'approved']) && $canApprove)
                <x-text-input wire:model="reviewNote" placeholder="Reason if returning" class="w-48" />
                <x-secondary-button wire:click="reject">Return to draft</x-secondary-button>
            @endif
            @if ($run->status === 'submitted' && $canApprove && ! $conflicted)
                <x-primary-button wire:click="approve" wire:confirm="Approve this payroll?">Approve</x-primary-button>
            @elseif ($run->status === 'submitted' && $canApprove)
                <span class="text-xs text-amber-700">A different user must approve (you prepared or submitted it).</span>
            @endif
            @if ($run->status === 'approved' && $canLock)
                <x-primary-button wire:click="lock" wire:confirm="Lock the payroll and publish payslips to employees? This cannot be undone.">Lock &amp; publish</x-primary-button>
            @endif
        </div>
        @error('reviewNote')<p class="w-full text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="mb-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-5">
        @foreach (['Employees' => $totals->c, 'Gross' => $money($totals->gross), 'Deductions' => $money($totals->ded), 'Net pay' => $money($totals->net), 'Employer EPF/ETF' => $money($totals->er)] as $label => $value)
            <div class="border border-zinc-200 rounded-xl bg-white p-3"><div class="text-xs text-zinc-500">{{ $label }}</div><div class="text-lg font-semibold">{{ $value }}</div></div>
        @endforeach
    </div>

    @if (! empty($run->warnings))
        <details class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <summary class="cursor-pointer font-medium">{{ count($run->warnings) }} warning(s) to review</summary>
            <ul class="mt-2 list-disc ps-5">@foreach ($run->warnings as $w)<li>{{ $w }}</li>@endforeach</ul>
        </details>
    @endif

    <x-text-input wire:model.live.debounce.300ms="search" placeholder="Search name or code…" class="mb-4 w-full sm:w-72" />

    <div class="border border-zinc-200 overflow-x-auto rounded-xl bg-white">
        <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500"><tr><th class="px-4 py-3">Employee</th><th class="px-4 py-3 text-right">Basic</th><th class="px-4 py-3 text-right">Gross</th><th class="px-4 py-3 text-right">Deductions</th><th class="px-4 py-3 text-right">Net</th><th class="px-4 py-3"></th></tr></thead>
            <tbody class="divide-y divide-zinc-100">
                @forelse ($payslips as $p)
                    <tr wire:key="ps-{{ $p->id }}">
                        <td class="px-4 py-3"><span class="font-mono text-xs text-zinc-500">{{ $p->employee_no }}</span> {{ $p->employee_name }}</td>
                        <td class="px-4 py-3 text-right">{{ $money($p->basic_salary) }}</td>
                        <td class="px-4 py-3 text-right">{{ $money($p->gross) }}</td>
                        <td class="px-4 py-3 text-right">{{ $money($p->total_deductions) }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ $p->net_salary < 0 ? 'text-red-600' : '' }}">{{ $money($p->net_salary) }}</td>
                        <td class="space-x-3 px-4 py-3 text-right"><button wire:click="togglePayslip({{ $p->id }})" class="text-indigo-600 hover:underline">{{ $openPayslipId === $p->id ? 'Hide' : 'Details' }}</button><a href="{{ route('payslips.pdf', $p) }}" class="text-zinc-600 hover:underline">PDF</a></td>
                    </tr>
                    @if ($openPayslipId === $p->id)
                        <tr wire:key="psd-{{ $p->id }}" class="bg-zinc-50">
                            <td colspan="6" class="px-4 py-4">
                                <table class="w-full max-w-xl text-sm">
                                    @foreach ($p->lines as $l)
                                        <tr class="{{ $l->type === 'employer' ? 'text-zinc-400' : '' }}">
                                            <td class="py-1">{{ $l->description }} <span class="text-xs text-zinc-400">({{ $l->type }}{{ $l->is_manual ? ', manual' : '' }})</span></td>
                                            <td class="py-1 text-right">{{ $money($l->amount) }}</td>
                                            <td class="ps-3 py-1">@if ($l->is_manual && $draft && $canProcess)<button wire:click="removeLine({{ $l->id }})" class="text-xs text-red-600 hover:underline">remove</button>@endif</td>
                                        </tr>
                                    @endforeach
                                </table>
                                <p class="mt-2 text-xs text-zinc-500">No-pay days: {{ $p->no_pay_days }} · OT: {{ round($p->ot_weekday_minutes / 60, 2) }} h working-day, {{ round($p->ot_holiday_minutes / 60, 2) }} h off-day · EPF/ETF base: {{ $money($p->epf_base) }}</p>
                                @if ($draft && $canProcess)
                                    <form wire:submit="addLine" class="mt-3 flex flex-wrap items-end gap-2">
                                        <select wire:model="lineType" class="rounded-lg border-zinc-300 text-sm shadow-xs"><option value="earning">Earning</option><option value="deduction">Deduction</option></select>
                                        <x-field label="" error="lineDescription"><x-text-input wire:model="lineDescription" placeholder="Description (bonus, advance…)" class="w-64" /></x-field>
                                        <x-field label="" error="lineAmount"><x-text-input type="number" step="0.01" wire:model="lineAmount" placeholder="Amount" class="w-32" /></x-field>
                                        <x-secondary-button type="submit">Add line</x-secondary-button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">No payslips.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payslips->links() }}</div>
</div>
