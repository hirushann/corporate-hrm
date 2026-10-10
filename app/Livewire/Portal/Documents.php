<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\CompanyDocument;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Company documents')]
class Documents extends Component
{
    use AuthorizesPermission;

    public function mount(): void
    {
        $this->allow('documents.view');
    }

    public function render()
    {
        return view('livewire.portal.documents', [
            'documents' => CompanyDocument::orderBy('category')->orderBy('title')->get()->groupBy('category'),
            'categories' => CompanyDocument::CATEGORIES,
        ]);
    }
}
