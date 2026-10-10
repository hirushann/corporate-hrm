<?php

namespace App\Livewire\Concerns;

trait AuthorizesPermission
{
    /** Livewire actions are separate requests, so check inside each one as well as in mount(). */
    protected function allow(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }
}
