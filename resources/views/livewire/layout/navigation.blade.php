<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    @can('employees.view')
                        <x-nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*')" wire:navigate>
                            {{ __('Employees') }}
                        </x-nav-link>
                    @endcan
                    @can('organization.view')
                        <div class="hidden sm:flex sm:items-center">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none">{{ __('Organisation') }} <span class="ms-1">▾</span></button>
                                </x-slot>
                                <x-slot name="content">
                                    <x-dropdown-link :href="route('organization.company')" wire:navigate>{{ __('Company') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('organization.branches')" wire:navigate>{{ __('Branches') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('organization.departments')" wire:navigate>{{ __('Departments') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('organization.designations')" wire:navigate>{{ __('Designations') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('organization.categories')" wire:navigate>{{ __('Categories') }}</x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endcan
                    @canany(['attendance.view', 'attendance.adjust', 'shifts.view'])
                        <div class="hidden sm:flex sm:items-center">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none">{{ __('Attendance') }} <span class="ms-1">▾</span></button>
                                </x-slot>
                                <x-slot name="content">
                                    @can('attendance.view')<x-dropdown-link :href="route('attendance.index')" wire:navigate>{{ __('Daily & monthly') }}</x-dropdown-link>@endcan
                                    @can('attendance.adjust')<x-dropdown-link :href="route('attendance.adjustments')" wire:navigate>{{ __('Adjustments') }}</x-dropdown-link>@endcan
                                    @can('shifts.view')<x-dropdown-link :href="route('attendance.roster')" wire:navigate>{{ __('Shift roster') }}</x-dropdown-link>@endcan
                                    @can('shifts.view')<x-dropdown-link :href="route('attendance.shifts')" wire:navigate>{{ __('Shifts') }}</x-dropdown-link>@endcan
                                    @can('shifts.view')<x-dropdown-link :href="route('attendance.holidays')" wire:navigate>{{ __('Holidays') }}</x-dropdown-link>@endcan
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endcanany
                    @canany(['leave.view'])
                        <div class="hidden sm:flex sm:items-center">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none">{{ __('Leave') }} <span class="ms-1">▾</span></button>
                                </x-slot>
                                <x-slot name="content">
                                    @can('leave.view')<x-dropdown-link :href="route('leave.requests')" wire:navigate>{{ __('Requests') }}</x-dropdown-link>@endcan
                                    @can('leave.view')<x-dropdown-link :href="route('leave.balances')" wire:navigate>{{ __('Balances') }}</x-dropdown-link>@endcan
                                    @can('leave.view')<x-dropdown-link :href="route('leave.types')" wire:navigate>{{ __('Leave types') }}</x-dropdown-link>@endcan
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endcanany
                    @can('payroll.view')
                        <div class="hidden sm:flex sm:items-center">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none">{{ __('Payroll') }} <span class="ms-1">▾</span></button>
                                </x-slot>
                                <x-slot name="content">
                                    <x-dropdown-link :href="route('payroll.index')" wire:navigate>{{ __('Payroll runs') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('payroll.components')" wire:navigate>{{ __('Salary components') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('payroll.settings')" wire:navigate>{{ __('Settings') }}</x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endcan
                    @can('portal.use')
                        <div class="hidden sm:flex sm:items-center">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none">{{ __('My Portal') }} <span class="ms-1">▾</span></button>
                                </x-slot>
                                <x-slot name="content">
                                    <x-dropdown-link :href="route('portal.profile')" wire:navigate>{{ __('My profile') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('portal.payslips')" wire:navigate>{{ __('My payslips') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('portal.attendance')" wire:navigate>{{ __('My attendance') }}</x-dropdown-link>
                                    <x-dropdown-link :href="route('portal.leave')" wire:navigate>{{ __('My leave') }}</x-dropdown-link>
                                    @can('notices.view')<x-dropdown-link :href="route('portal.notices')" wire:navigate>{{ __('Notices') }}</x-dropdown-link>@endcan
                                    @can('documents.view')<x-dropdown-link :href="route('portal.documents')" wire:navigate>{{ __('Company documents') }}</x-dropdown-link>@endcan
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endcan
                    @canany(['users.view', 'audit.view', 'notices.manage', 'documents.manage'])
                        <div class="hidden sm:flex sm:items-center">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-1 pt-1 text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none">{{ __('Administration') }} <span class="ms-1">▾</span></button>
                                </x-slot>
                                <x-slot name="content">
                                    @can('users.view')<x-dropdown-link :href="route('admin.users')" wire:navigate>{{ __('Users') }}</x-dropdown-link>@endcan
                                    @can('notices.manage')<x-dropdown-link :href="route('admin.notices')" wire:navigate>{{ __('Manage notices') }}</x-dropdown-link>@endcan
                                    @can('documents.manage')<x-dropdown-link :href="route('admin.documents')" wire:navigate>{{ __('Manage documents') }}</x-dropdown-link>@endcan
                                    @can('audit.view')<x-dropdown-link :href="route('admin.audit')" wire:navigate>{{ __('Audit log') }}</x-dropdown-link>@endcan
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endcanany
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            @can('employees.view')<x-responsive-nav-link :href="route('employees.index')" wire:navigate>{{ __('Employees') }}</x-responsive-nav-link>@endcan
            @can('organization.view')
                <x-responsive-nav-link :href="route('organization.company')" wire:navigate>{{ __('Company') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('organization.branches')" wire:navigate>{{ __('Branches') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('organization.departments')" wire:navigate>{{ __('Departments') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('organization.designations')" wire:navigate>{{ __('Designations') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('organization.categories')" wire:navigate>{{ __('Categories') }}</x-responsive-nav-link>
            @endcan
            @can('attendance.view')<x-responsive-nav-link :href="route('attendance.index')" wire:navigate>{{ __('Attendance') }}</x-responsive-nav-link>@endcan
            @can('attendance.adjust')<x-responsive-nav-link :href="route('attendance.adjustments')" wire:navigate>{{ __('Adjustments') }}</x-responsive-nav-link>@endcan
            @can('shifts.view')
                <x-responsive-nav-link :href="route('attendance.roster')" wire:navigate>{{ __('Shift roster') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('attendance.shifts')" wire:navigate>{{ __('Shifts') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('attendance.holidays')" wire:navigate>{{ __('Holidays') }}</x-responsive-nav-link>
            @endcan
            @can('leave.view')
                <x-responsive-nav-link :href="route('leave.requests')" wire:navigate>{{ __('Leave requests') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('leave.balances')" wire:navigate>{{ __('Leave balances') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('leave.types')" wire:navigate>{{ __('Leave types') }}</x-responsive-nav-link>
            @endcan
            @can('payroll.view')
                <x-responsive-nav-link :href="route('payroll.index')" wire:navigate>{{ __('Payroll runs') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('payroll.components')" wire:navigate>{{ __('Salary components') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('payroll.settings')" wire:navigate>{{ __('Payroll settings') }}</x-responsive-nav-link>
            @endcan
            @can('portal.use')
                <x-responsive-nav-link :href="route('portal.profile')" wire:navigate>{{ __('My profile') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('portal.payslips')" wire:navigate>{{ __('My payslips') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('portal.attendance')" wire:navigate>{{ __('My attendance') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('portal.leave')" wire:navigate>{{ __('My leave') }}</x-responsive-nav-link>
            @endcan
            @can('notices.view')<x-responsive-nav-link :href="route('portal.notices')" wire:navigate>{{ __('Notices') }}</x-responsive-nav-link>@endcan
            @can('documents.view')<x-responsive-nav-link :href="route('portal.documents')" wire:navigate>{{ __('Company documents') }}</x-responsive-nav-link>@endcan
            @can('notices.manage')<x-responsive-nav-link :href="route('admin.notices')" wire:navigate>{{ __('Manage notices') }}</x-responsive-nav-link>@endcan
            @can('documents.manage')<x-responsive-nav-link :href="route('admin.documents')" wire:navigate>{{ __('Manage documents') }}</x-responsive-nav-link>@endcan
            @can('users.view')<x-responsive-nav-link :href="route('admin.users')" wire:navigate>{{ __('Users') }}</x-responsive-nav-link>@endcan
            @can('audit.view')<x-responsive-nav-link :href="route('admin.audit')" wire:navigate>{{ __('Audit log') }}</x-responsive-nav-link>@endcan
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm text-gray-500">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
