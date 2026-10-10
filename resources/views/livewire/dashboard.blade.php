<div class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
    <div>
        <h1 class="text-2xl font-semibold text-gray-800">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h1>
        @if ($employee)<p class="text-sm text-gray-500">{{ $employee->designation->name }} · {{ $employee->department->name }} · {{ $employee->employee_no }}</p>@endif
    </div>

    @if ($employee)
        <div class="grid gap-4 sm:grid-cols-3">
            <a href="{{ route('portal.payslips') }}" wire:navigate class="rounded-lg bg-white p-4 shadow-sm hover:bg-gray-50">
                <div class="text-xs uppercase text-gray-500">Latest payslip</div>
                @if ($latestPayslip)
                    <div class="mt-1 text-lg font-semibold">{{ $latestPayslip->run->label() }}</div>
                    <div class="text-sm text-gray-500">Net {{ number_format($latestPayslip->net_salary, 2) }}</div>
                @else
                    <div class="mt-1 text-sm text-gray-500">None published yet</div>
                @endif
            </a>
            <a href="{{ route('portal.leave') }}" wire:navigate class="rounded-lg bg-white p-4 shadow-sm hover:bg-gray-50"><div class="text-xs uppercase text-gray-500">Leave</div><div class="mt-1 text-sm text-gray-700">Check balances or request leave</div></a>
            <a href="{{ route('portal.attendance') }}" wire:navigate class="rounded-lg bg-white p-4 shadow-sm hover:bg-gray-50"><div class="text-xs uppercase text-gray-500">Attendance</div><div class="mt-1 text-sm text-gray-700">See this month's summary</div></a>
        </div>
    @elseif (auth()->user()->can('portal.use'))
        <p class="rounded-md bg-amber-50 p-3 text-sm text-amber-800">No employee profile is linked to your account, so the self-service pages are not available.</p>
    @endif

    @if ($notices->isNotEmpty())
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <div class="mb-3 flex items-center justify-between"><h2 class="font-medium text-gray-900">Notices</h2><a href="{{ route('portal.notices') }}" wire:navigate class="text-sm text-indigo-600 hover:underline">View all</a></div>
            <ul class="space-y-3 text-sm">
                @foreach ($notices as $n)
                    <li><span class="font-medium">{{ $n->is_pinned ? '📌 ' : '' }}{{ $n->title }}</span> <span class="text-xs text-gray-400">{{ $n->published_at->format('Y-m-d') }}</span><p class="text-gray-600">{{ \Illuminate\Support\Str::limit($n->body, 140) }}</p></li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
