<?php

namespace App\Livewire\Organization;

use App\Livewire\Concerns\AuthorizesPermission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Generic list + modal form for simple master-data tables
 * (branches, departments, designations, employee categories).
 */
#[Layout('layouts.app')]
abstract class LookupCrud extends Component
{
    use AuthorizesPermission, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    /** @return class-string<Model> */
    abstract protected function model(): string;

    abstract protected function heading(): string;

    /** @return array<string, array{label: string, type?: string}> */
    abstract protected function fields(): array;

    /** @return array<string, array> validation rules per form field */
    abstract protected function fieldRules(): array;

    /** Extra attributes merged in when creating (e.g. company_id). */
    protected function defaults(): array
    {
        return [];
    }

    /** Permission needed to see / change this master data. */
    protected function viewPermission(): string
    {
        return 'organization.view';
    }

    protected function managePermission(): string
    {
        return 'organization.manage';
    }

    protected function orderColumn(): string
    {
        return 'name';
    }

    protected function searchColumn(): string
    {
        return 'name';
    }

    public function mount(): void
    {
        $this->allow($this->viewPermission());
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    protected function blankForm(): array
    {
        return collect($this->fields())->map(fn ($f) => match ($f['type'] ?? 'text') {
            'checkbox' => true,
            'weekdays' => [1, 2, 3, 4, 5],
            default => $f['default'] ?? '',
        })->all();
    }

    public function create(): void
    {
        $this->allow($this->managePermission());
        $this->resetValidation();
        $this->editingId = null;
        $this->form = $this->blankForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->allow($this->managePermission());
        $this->resetValidation();
        $record = ($this->model())::findOrFail($id);
        $this->editingId = $id;
        $this->form = collect($this->fields())->map(function ($f, $key) use ($record) {
            $value = $record->{$key} ?? '';

            return match (true) {
                $value instanceof \DateTimeInterface => $value->format(($f['type'] ?? '') === 'date' ? 'Y-m-d' : 'H:i'),
                ($f['type'] ?? '') === 'time' => substr((string) $value, 0, 5),
                ($f['type'] ?? '') === 'weekdays' => array_map('intval', (array) $value),
                default => $value,
            };
        })->all();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->allow($this->managePermission());

        $rules = collect($this->fieldRules())->mapWithKeys(fn ($r, $key) => ["form.$key" => $r])->all();
        $data = $this->validate($rules)['form'];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        $model = $this->model();
        if ($this->editingId) {
            $model::findOrFail($this->editingId)->update($data);
        } else {
            $model::create($data + $this->defaults());
        }

        $this->showForm = false;
        session()->flash('status', $this->heading().' saved.');
    }

    public function toggleActive(int $id): void
    {
        $this->allow($this->managePermission());
        $record = ($this->model())::findOrFail($id);
        $record->update(['is_active' => ! $record->is_active]);
    }

    public function delete(int $id): void
    {
        $this->allow($this->managePermission());
        abort_if(array_key_exists('is_active', $this->fields()), 403);

        ($this->model())::findOrFail($id)->delete();
        session()->flash('status', 'Deleted.');
    }

    /** Unique rule that ignores the record being edited. */
    protected function unique(string $column): Unique
    {
        return Rule::unique((new ($this->model()))->getTable(), $column)->ignore($this->editingId);
    }

    public function render()
    {
        $query = ($this->model())::query()->orderBy($this->orderColumn());
        if (filled($this->search)) {
            $query->where($this->searchColumn(), 'like', "%{$this->search}%");
        }

        return view('livewire.organization.lookup', [
            'rows' => $query->paginate(15),
            'heading' => $this->heading(),
            'fields' => $this->fields(),
            'canManage' => auth()->user()->can($this->managePermission()),
            'canToggle' => array_key_exists('is_active', $this->fields()),
        ])->title($this->heading());
    }
}
