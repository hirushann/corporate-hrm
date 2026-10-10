<?php

namespace App\Livewire\Admin;

use App\Livewire\Organization\LookupCrud;
use App\Models\Department;
use App\Models\Notice;

class Notices extends LookupCrud
{
    protected function model(): string
    {
        return Notice::class;
    }

    protected function heading(): string
    {
        return 'Notices';
    }

    protected function viewPermission(): string
    {
        return 'notices.manage';
    }

    protected function managePermission(): string
    {
        return 'notices.manage';
    }

    protected function orderColumn(): string
    {
        return 'published_at';
    }

    protected function orderDirection(): string
    {
        return 'desc';
    }

    protected function searchColumn(): string
    {
        return 'title';
    }

    protected function fields(): array
    {
        return [
            'title' => ['label' => 'Title'],
            'body' => ['label' => 'Message', 'type' => 'textarea', 'hidden' => true],
            'department_id' => ['label' => 'Audience', 'type' => 'select', 'options' => ['' => 'All employees'] + Department::orderBy('name')->pluck('name', 'id')->all()],
            'published_at' => ['label' => 'Publish on', 'type' => 'date', 'default' => today()->toDateString()],
            'expires_at' => ['label' => 'Expires on (optional)', 'type' => 'date'],
            'is_pinned' => ['label' => 'Pin to top', 'type' => 'checkbox'],
        ];
    }

    protected function fieldRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'published_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:form.published_at'],
            'is_pinned' => ['boolean'],
        ];
    }

    protected function defaults(): array
    {
        return ['created_by' => auth()->id()];
    }

    /** Pinning is off by default, unlike the "Active" checkboxes on other lists. */
    protected function blankForm(): array
    {
        return array_merge(parent::blankForm(), ['is_pinned' => false]);
    }
}
