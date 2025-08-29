<?php

declare(strict_types=1);

namespace App\Livewire\Panel;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Sidebar extends Component
{
    public array $links = [];

    public function mount(): void
    {
        $user = Auth::user();

        $baseLinks = [
            ['label' => 'Dashboard', 'icon' => 'fa-chart-line', 'route' => 'dashboard'],
            ['label' => 'Mis Archivos', 'icon' => 'fa-file-csv', 'route' => 'uploads.index'],
            ['label' => 'Subir CSV', 'icon' => 'fa-upload', 'route' => 'uploads.create'],
        ];

        $adminLinks = [
            ['label' => 'Admin', 'icon' => 'fa-cog', 'route' => 'admin.index'],
            ['label' => 'Usuarios', 'icon' => 'fa-users', 'route' => 'admin.users.index'],
        ];

        $this->links = $baseLinks;

        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            $this->links = array_merge($this->links, $adminLinks);
        }
    }

    public function render()
    {
        return view('livewire.panel.sidebar');
    }
}


