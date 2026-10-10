<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\CompanyDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Company documents')]
class CompanyDocuments extends Component
{
    use AuthorizesPermission, WithFileUploads;

    public string $title = '';

    public string $category = 'policy';

    public $file = null;

    public function mount(): void
    {
        $this->allow('documents.manage');
    }

    public function upload(): void
    {
        $this->allow('documents.manage');

        $this->validate([
            'title' => 'required|string|max:255',
            'category' => ['required', Rule::in(array_keys(CompanyDocument::CATEGORIES))],
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
        ]);

        CompanyDocument::create([
            'title' => $this->title, 'category' => $this->category,
            'path' => $this->file->store('company-documents', 'local'),
            'original_name' => $this->file->getClientOriginalName(), 'mime_type' => $this->file->getMimeType(),
            'size' => $this->file->getSize(), 'uploaded_by' => auth()->id(),
        ]);

        $this->reset('title', 'file');
        session()->flash('status', 'Document uploaded.');
    }

    public function delete(int $id): void
    {
        $this->allow('documents.manage');

        $document = CompanyDocument::findOrFail($id);
        Storage::disk('local')->delete($document->path);
        $document->delete();
        session()->flash('status', 'Document deleted.');
    }

    public function render()
    {
        return view('livewire.admin.company-documents', ['documents' => CompanyDocument::latest()->get(), 'categories' => CompanyDocument::CATEGORIES]);
    }
}
