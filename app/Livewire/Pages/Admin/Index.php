<?php

declare(strict_types=1);

namespace App\Livewire\Pages\Admin;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('livewire.pages.admin.index')
            ->layout('layouts.panel');
    }
}


