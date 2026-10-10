@php
    $sections = [
        'Employment' => [
            'Employee code' => $employee->employee_no, 'Designation' => $employee->designation->name, 'Department' => $employee->department->name,
            'Branch' => $employee->branch->name, 'Category' => $employee->category->name, 'Reports to' => $employee->manager?->full_name,
            'Join date' => $employee->join_date->format('Y-m-d'), 'Status' => ucfirst($employee->status),
        ],
        'Personal' => [
            'Full name' => $employee->full_name, 'NIC' => $employee->nic, 'Date of birth' => $employee->date_of_birth?->format('Y-m-d'),
            'Phone' => $employee->phone, 'Personal email' => $employee->personal_email, 'Address' => $employee->address,
            'Emergency contact' => trim($employee->emergency_contact_name.' '.$employee->emergency_contact_phone),
        ],
        'Statutory' => ['EPF number' => $employee->epf_number, 'ETF number' => $employee->etf_number],
    ];
@endphp
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6">
    <x-page-header title="My profile" />
    <p class="mb-4 text-sm text-gray-500">To change any of these details, please contact HR.</p>
    <div class="space-y-6">
        @foreach ($sections as $heading => $items)
            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h2 class="mb-3 font-medium text-gray-900">{{ $heading }}</h2>
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    @foreach ($items as $label => $value)<div><dt class="text-gray-500">{{ $label }}</dt><dd class="text-gray-900">{{ filled($value) ? $value : '—' }}</dd></div>@endforeach
                </dl>
            </section>
        @endforeach
    </div>
</div>
