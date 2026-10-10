@php $m = fn ($v) => number_format((float) $v, 2); @endphp
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    h1 { font-size: 16px; margin: 0; } .muted { color: #666; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    td, th { padding: 4px 6px; } th { text-align: left; background: #f1f1f1; }
    .right { text-align: right; } .total td { border-top: 1px solid #333; font-weight: bold; }
    .box { border: 1px solid #ccc; padding: 8px; margin-top: 10px; }
</style></head>
<body>
    <h1>{{ $company?->name ?? config('app.name') }}</h1>
    <div class="muted">{{ $company?->address }}</div>
    <h2 style="margin:12px 0 0">Payslip — {{ $payslip->run->label() }}</h2>

    <div class="box">
        <table style="margin:0">
            <tr><td><b>Employee</b></td><td>{{ $payslip->employee_name }} ({{ $payslip->employee_no }})</td><td><b>EPF No</b></td><td>{{ $payslip->epf_number ?: '—' }}</td></tr>
            <tr><td><b>Department</b></td><td>{{ $payslip->department }}</td><td><b>Designation</b></td><td>{{ $payslip->designation }}</td></tr>
        </table>
    </div>

    <table>
        <tr><th colspan="2">Earnings</th></tr>
        @foreach ($payslip->lines->where('type', 'earning') as $l)<tr><td>{{ $l->description }}</td><td class="right">{{ $m($l->amount) }}</td></tr>@endforeach
        <tr class="total"><td>Gross earnings</td><td class="right">{{ $m($payslip->gross) }}</td></tr>
    </table>
    <table>
        <tr><th colspan="2">Deductions</th></tr>
        @foreach ($payslip->lines->where('type', 'deduction') as $l)<tr><td>{{ $l->description }}</td><td class="right">{{ $m($l->amount) }}</td></tr>@endforeach
        <tr class="total"><td>Total deductions</td><td class="right">{{ $m($payslip->total_deductions) }}</td></tr>
    </table>
    <table><tr class="total"><td style="font-size:13px">NET SALARY</td><td class="right" style="font-size:13px">{{ $m($payslip->net_salary) }}</td></tr></table>

    @if ($payslip->lines->where('type', 'employer')->count())
        <table>
            <tr><th colspan="2">Employer contributions (not deducted from salary)</th></tr>
            @foreach ($payslip->lines->where('type', 'employer') as $l)<tr><td>{{ $l->description }}</td><td class="right">{{ $m($l->amount) }}</td></tr>@endforeach
        </table>
    @endif

    <p class="muted" style="margin-top:14px">Computer-generated payslip. Confidential.</p>
</body></html>
