<?php

namespace App\Livewire\Portal;

use App\Livewire\Concerns\ResolvesEmployee;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('My profile')]
class Profile extends Component
{
    use ResolvesEmployee;

    public function render()
    {
        return view('livewire.portal.profile', ['employee' => $this->myEmployee()]);
    }
}
