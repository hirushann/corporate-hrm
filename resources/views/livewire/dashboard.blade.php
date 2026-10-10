@php
    $icons = ['headcount' => 'users', 'present' => 'check-circle', 'absent' => 'x-circle', 'leave' => 'calendar-days', 'leave_pending' => 'inbox', 'adjust_pending' => 'clock', 'payroll_pending' => 'banknotes'];
    $tones = ['present' => 'text-green-600 bg-green-50', 'absent' => 'text-red-600 bg-red-50', 'leave' => 'text-blue-600 bg-blue-50', 'leave_pending' => 'text-amber-600 bg-amber-50', 'adjust_pending' => 'text-amber-600 bg-amber-50', 'payroll_pending' => 'text-amber-600 bg-amber-50'];
@endphp
<div class="max-w-6xl space-y-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}</flux:heading>
        <flux:text class="mt-1">
            @if ($employee){{ $employee->designation->name }} · {{ $employee->department->name }} · {{ $employee->employee_no }}@else{{ now()->format('l, j F Y') }}@endif
        </flux:text>
    </div>

    @if ($stats)
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($stats as $key => $stat)
                <a @if ($stat['route']) href="{{ $stat['route'] }}" wire:navigate @endif class="group rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-indigo-300 hover:shadow-sm">
                    <div class="flex items-center justify-between">
                        <flux:text size="sm">{{ $stat['label'] }}</flux:text>
                        <span class="flex size-8 items-center justify-center rounded-lg {{ $tones[$key] ?? 'text-indigo-600 bg-indigo-50' }}"><flux:icon :name="$icons[$key] ?? 'chart-bar'" variant="mini" class="size-4" /></span>
                    </div>
                    <div class="mt-3 text-3xl font-semibold tracking-tight text-zinc-900">{{ $stat['value'] }}</div>
                </a>
            @endforeach
        </div>
    @endif

    @if ($employee)
        <div>
            <flux:heading size="lg" class="mb-3">{{ __('My workspace') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-3">
                <a href="{{ route('portal.payslips') }}" wire:navigate class="rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-indigo-300 hover:shadow-sm">
                    <div class="flex items-center gap-2 text-zinc-500"><flux:icon name="banknotes" variant="mini" class="size-4" /><flux:text size="sm">{{ __('Latest payslip') }}</flux:text></div>
                    @if ($latestPayslip)
                        <div class="mt-2 text-lg font-semibold text-zinc-900">{{ $latestPayslip->run->label() }}</div>
                        <flux:text size="sm">{{ __('Net pay') }} {{ number_format($latestPayslip->net_salary, 2) }}</flux:text>
                    @else
                        <flux:text size="sm" class="mt-2">{{ __('None published yet') }}</flux:text>
                    @endif
                </a>
                <a href="{{ route('portal.leave') }}" wire:navigate class="rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-indigo-300 hover:shadow-sm">
                    <div class="flex items-center gap-2 text-zinc-500"><flux:icon name="calendar-days" variant="mini" class="size-4" /><flux:text size="sm">{{ __('Leave') }}</flux:text></div>
                    <flux:text class="mt-2">{{ __('Check balances or request leave') }}</flux:text>
                </a>
                <a href="{{ route('portal.attendance') }}" wire:navigate class="rounded-xl border border-zinc-200 bg-white p-5 transition hover:border-indigo-300 hover:shadow-sm">
                    <div class="flex items-center gap-2 text-zinc-500"><flux:icon name="clock" variant="mini" class="size-4" /><flux:text size="sm">{{ __('Attendance') }}</flux:text></div>
                    <flux:text class="mt-2">{{ __("See this month's summary") }}</flux:text>
                </a>
            </div>
        </div>
    @elseif (auth()->user()->can('portal.use'))
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('No employee profile is linked to your account')" :text="__('The self-service pages are available once HR links your login to an employee record.')" />
    @endif

    @if ($notices->isNotEmpty())
        <div class="rounded-xl border border-zinc-200 bg-white p-6">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Notices') }}</flux:heading>
                <flux:button size="sm" variant="ghost" :href="route('portal.notices')" wire:navigate>{{ __('View all') }}</flux:button>
            </div>
            <ul class="divide-y divide-zinc-100">
                @foreach ($notices as $n)
                    <li class="py-3 first:pt-0 last:pb-0">
                        <div class="flex items-center gap-2">
                            @if ($n->is_pinned)<flux:badge size="sm" color="amber">{{ __('Pinned') }}</flux:badge>@endif
                            <span class="font-medium text-zinc-900">{{ $n->title }}</span>
                            <span class="text-xs text-zinc-400">{{ $n->published_at->format('Y-m-d') }}</span>
                        </div>
                        <flux:text size="sm" class="mt-1">{{ \Illuminate\Support\Str::limit($n->body, 160) }}</flux:text>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
