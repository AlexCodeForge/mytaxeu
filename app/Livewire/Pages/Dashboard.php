<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.pages.dashboard')
            ->layout('layouts.panel');
    }
}


