<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans antialiased">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-white">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" wire:navigate class="me-5 flex items-center gap-2 px-2 py-1">
                <span class="flex size-8 items-center justify-center rounded-lg bg-indigo-600 text-sm font-bold text-white">HR</span>
                <span class="text-sm font-semibold text-zinc-900">{{ config('app.name') }}</span>
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>

                @if (auth()->user()->can('portal.use') && auth()->user()->employee)
                    <flux:navlist.group :heading="__('My portal')" expandable :expanded="request()->routeIs('portal.*') && ! request()->routeIs('portal.notices', 'portal.documents')">
                        <flux:navlist.item :href="route('portal.profile')" :current="request()->routeIs('portal.profile')" wire:navigate>{{ __('My profile') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('portal.payslips')" :current="request()->routeIs('portal.payslips')" wire:navigate>{{ __('My payslips') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('portal.attendance')" :current="request()->routeIs('portal.attendance')" wire:navigate>{{ __('My attendance') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('portal.leave')" :current="request()->routeIs('portal.leave')" wire:navigate>{{ __('My leave') }}</flux:navlist.item>
                    </flux:navlist.group>
                @endif
                @can('notices.view')
                    <flux:navlist.item icon="megaphone" :href="route('portal.notices')" :current="request()->routeIs('portal.notices')" wire:navigate>{{ __('Notices') }}</flux:navlist.item>
                @endcan
                @can('documents.view')
                    <flux:navlist.item icon="document-text" :href="route('portal.documents')" :current="request()->routeIs('portal.documents')" wire:navigate>{{ __('Documents') }}</flux:navlist.item>
                @endcan

                @can('employees.view')
                    <flux:navlist.item icon="users" :href="route('employees.index')" :current="request()->routeIs('employees.*')" wire:navigate>{{ __('Employees') }}</flux:navlist.item>
                @endcan

                @can('organization.view')
                    <flux:navlist.group :heading="__('Organisation')" icon="building-office" expandable :expanded="request()->routeIs('organization.*')">
                        <flux:navlist.item :href="route('organization.company')" :current="request()->routeIs('organization.company')" wire:navigate>{{ __('Company') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('organization.branches')" :current="request()->routeIs('organization.branches')" wire:navigate>{{ __('Branches') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('organization.departments')" :current="request()->routeIs('organization.departments')" wire:navigate>{{ __('Departments') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('organization.designations')" :current="request()->routeIs('organization.designations')" wire:navigate>{{ __('Designations') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('organization.categories')" :current="request()->routeIs('organization.categories')" wire:navigate>{{ __('Categories') }}</flux:navlist.item>
                    </flux:navlist.group>
                @endcan

                @canany(['attendance.view', 'attendance.adjust', 'shifts.view'])
                    <flux:navlist.group :heading="__('Attendance')" icon="clock" expandable :expanded="request()->routeIs('attendance.*')">
                        @can('attendance.view')<flux:navlist.item :href="route('attendance.index')" :current="request()->routeIs('attendance.index')" wire:navigate>{{ __('Daily & monthly') }}</flux:navlist.item>@endcan
                        @can('attendance.manage')<flux:navlist.item :href="route('attendance.import')" :current="request()->routeIs('attendance.import')" wire:navigate>{{ __('Import punches') }}</flux:navlist.item>@endcan
                        @can('attendance.adjust')<flux:navlist.item :href="route('attendance.adjustments')" :current="request()->routeIs('attendance.adjustments')" wire:navigate>{{ __('Adjustments') }}</flux:navlist.item>@endcan
                        @can('shifts.view')
                            <flux:navlist.item :href="route('attendance.roster')" :current="request()->routeIs('attendance.roster')" wire:navigate>{{ __('Shift roster') }}</flux:navlist.item>
                            <flux:navlist.item :href="route('attendance.shifts')" :current="request()->routeIs('attendance.shifts')" wire:navigate>{{ __('Shifts') }}</flux:navlist.item>
                            <flux:navlist.item :href="route('attendance.holidays')" :current="request()->routeIs('attendance.holidays')" wire:navigate>{{ __('Holidays') }}</flux:navlist.item>
                        @endcan
                    </flux:navlist.group>
                @endcanany

                @can('leave.view')
                    <flux:navlist.group :heading="__('Leave')" icon="calendar-days" expandable :expanded="request()->routeIs('leave.*')">
                        <flux:navlist.item :href="route('leave.requests')" :current="request()->routeIs('leave.requests')" wire:navigate>{{ __('Requests') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('leave.balances')" :current="request()->routeIs('leave.balances')" wire:navigate>{{ __('Balances') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('leave.types')" :current="request()->routeIs('leave.types')" wire:navigate>{{ __('Leave types') }}</flux:navlist.item>
                    </flux:navlist.group>
                @endcan

                @can('payroll.view')
                    <flux:navlist.group :heading="__('Payroll')" icon="banknotes" expandable :expanded="request()->routeIs('payroll.*')">
                        <flux:navlist.item :href="route('payroll.index')" :current="request()->routeIs('payroll.index', 'payroll.show')" wire:navigate>{{ __('Payroll runs') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('payroll.components')" :current="request()->routeIs('payroll.components')" wire:navigate>{{ __('Salary components') }}</flux:navlist.item>
                        <flux:navlist.item :href="route('payroll.settings')" :current="request()->routeIs('payroll.settings')" wire:navigate>{{ __('Settings') }}</flux:navlist.item>
                    </flux:navlist.group>
                @endcan

                @canany(['reports.attendance', 'reports.payroll', 'reports.statutory', 'reports.headcount'])
                    <flux:navlist.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>{{ __('Reports') }}</flux:navlist.item>
                @endcanany

                @canany(['users.view', 'audit.view', 'notices.manage', 'documents.manage'])
                    <flux:navlist.group :heading="__('Administration')" icon="cog-6-tooth" expandable :expanded="request()->routeIs('admin.*')">
                        @can('users.view')<flux:navlist.item :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>{{ __('Users') }}</flux:navlist.item>@endcan
                        @can('notices.manage')<flux:navlist.item :href="route('admin.notices')" :current="request()->routeIs('admin.notices')" wire:navigate>{{ __('Manage notices') }}</flux:navlist.item>@endcan
                        @can('documents.manage')<flux:navlist.item :href="route('admin.documents')" :current="request()->routeIs('admin.documents')" wire:navigate>{{ __('Manage documents') }}</flux:navlist.item>@endcan
                        @can('audit.view')<flux:navlist.item :href="route('admin.audit')" :current="request()->routeIs('admin.audit')" wire:navigate>{{ __('Audit log') }}</flux:navlist.item>@endcan
                    </flux:navlist.group>
                @endcanany
            </flux:navlist>

            <flux:spacer />

            <flux:dropdown position="top" align="start">
                <flux:profile :name="auth()->user()->name" :initials="\Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->map(fn ($p) => \Illuminate\Support\Str::substr($p, 0, 1))->take(2)->implode('')" icon-trailing="chevron-up-down" />

                <flux:menu class="w-56">
                    <div class="px-2 py-1.5 text-xs text-zinc-500">{{ auth()->user()->email }}</div>
                    <flux:menu.separator />
                    <flux:menu.item icon="user" :href="route('profile')" wire:navigate>{{ __('Account settings') }}</flux:menu.item>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">{{ __('Log out') }}</flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <flux:header class="border-b border-zinc-200 bg-white lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <flux:spacer />
            <span class="text-sm font-semibold text-zinc-900">{{ config('app.name') }}</span>
            <flux:spacer />
        </flux:header>

        <flux:main>
            @if (isset($header))
                <div class="mb-6">{{ $header }}</div>
            @endif

            {{ $slot }}
        </flux:main>

        @fluxScripts
    </body>
</html>
