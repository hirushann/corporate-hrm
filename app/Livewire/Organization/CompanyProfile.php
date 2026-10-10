<?php

namespace App\Livewire\Organization;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Company;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Company profile')]
class CompanyProfile extends Component
{
    use AuthorizesPermission;

    public string $name = '';

    public ?string $registration_no = null;

    public ?string $address = null;

    public ?string $phone = null;

    public ?string $email = null;

    public ?string $epf_registration_no = null;

    public ?string $etf_registration_no = null;

    public ?string $tax_registration_no = null;

    public function mount(): void
    {
        $this->allow('organization.view');
        $company = Company::first();
        if ($company) {
            $this->fill($company->only(['name', 'registration_no', 'address', 'phone', 'email', 'epf_registration_no', 'etf_registration_no', 'tax_registration_no']));
        }
    }

    public function save(): void
    {
        $this->allow('organization.manage');

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'registration_no' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'epf_registration_no' => 'nullable|string|max:100',
            'etf_registration_no' => 'nullable|string|max:100',
            'tax_registration_no' => 'nullable|string|max:100',
        ]);

        $company = Company::first();
        $company ? $company->update($data) : Company::create($data);

        session()->flash('status', 'Company profile saved.');
    }

    public function render()
    {
        return view('livewire.organization.company-profile', ['canManage' => auth()->user()->can('organization.manage')]);
    }
}
