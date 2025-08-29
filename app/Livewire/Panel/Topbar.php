<?php

declare(strict_types=1);

namespace App\Livewire\Panel;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Topbar extends Component
{
    public function render()
    {
        return view('livewire.panel.topbar', [
            'user' => Auth::user(),
        ]);
    }
}


