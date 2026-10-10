<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Users')]
class Users extends Component
{
    use AuthorizesPermission, WithPagination;

    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $role = Permissions::EMPLOYEE;

    public bool $is_active = true;

    public function mount(): void
    {
        $this->allow('users.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    private function isSuperAdmin(): bool
    {
        return auth()->user()->hasRole(Permissions::SUPER_ADMIN);
    }

    /** Only a Super Admin may grant, or modify holders of, the Super Admin role. */
    private function assignableRoles(): array
    {
        return Role::orderBy('name')->pluck('name')
            ->reject(fn ($r) => $r === Permissions::SUPER_ADMIN && ! $this->isSuperAdmin())
            ->values()->all();
    }

    private function guardTarget(User $user): void
    {
        abort_if($user->hasRole(Permissions::SUPER_ADMIN) && ! $this->isSuperAdmin(), 403);
    }

    public function create(): void
    {
        $this->allow('users.manage');
        $this->resetValidation();
        $this->reset('editingId', 'name', 'email');
        $this->role = Permissions::EMPLOYEE;
        $this->is_active = true;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->allow('users.manage');
        $this->resetValidation();
        $user = User::findOrFail($id);
        $this->guardTarget($user);

        $this->editingId = $id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->getRoleNames()->first() ?? Permissions::EMPLOYEE;
        $this->is_active = $user->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->allow('users.manage');

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role' => ['required', Rule::in($this->assignableRoles())],
            'is_active' => 'boolean',
        ]);

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $this->guardTarget($user);

            if ($user->is(auth()->user()) && (! $this->is_active || $this->role !== Permissions::SUPER_ADMIN && $user->hasRole(Permissions::SUPER_ADMIN))) {
                $this->addError('role', 'You cannot deactivate yourself or remove your own Super Admin role.');

                return;
            }

            $user->update(['name' => $this->name, 'email' => $this->email, 'is_active' => $this->is_active]);
            $user->syncRoles($this->role);
            $message = 'User updated.';
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Str::password(32),
                'is_active' => $this->is_active,
                'email_verified_at' => now(),
            ]);
            $user->assignRole($this->role);
            Password::sendResetLink(['email' => $user->email]);
            $message = 'User created. A password-setup link was emailed.';
        }

        activity()->performedOn($user)->causedBy(auth()->user())->withProperties(['role' => $this->role])->log('role set');

        $this->showForm = false;
        session()->flash('status', $message);
    }

    public function sendResetLink(int $id): void
    {
        $this->allow('users.manage');
        $user = User::findOrFail($id);
        $this->guardTarget($user);

        Password::sendResetLink(['email' => $user->email]);
        session()->flash('status', "Password reset link sent to {$user->email}.");
    }

    public function render()
    {
        $users = User::query()
            ->with('roles', 'employee')
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%")))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.users', [
            'users' => $users,
            'roles' => $this->assignableRoles(),
            'canManage' => auth()->user()->can('users.manage'),
        ]);
    }
}
