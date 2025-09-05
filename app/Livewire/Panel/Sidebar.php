<?php

declare(strict_types=1);

namespace App\Livewire\Panel;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Sidebar extends Component
{
    public array $userLinks = [];
    public array $adminLinks = [];
    public bool $isAdmin = false;

    public function mount(): void
    {
        $user = Auth::user();
        $this->isAdmin = $user && method_exists($user, 'isAdmin') && $user->isAdmin();

        $this->userLinks = [
            ['label' => 'Dashboard', 'icon' => 'fa-chart-line', 'route' => 'dashboard'],
            ['label' => 'Mis Archivos', 'icon' => 'fa-file-csv', 'route' => 'uploads.index'],
            ['label' => 'Subir CSV', 'icon' => 'fa-upload', 'route' => 'uploads.create'],
            ['label' => 'Facturación', 'icon' => 'fa-file-invoice-dollar', 'route' => 'billing'],
            ['label' => 'Suscripciones', 'icon' => 'fa-credit-card', 'route' => 'billing.subscriptions'],
        ];

        $this->adminLinks = [
            ['label' => 'Admin Dashboard', 'icon' => 'fa-tachometer-alt', 'route' => 'admin.index'],
            ['label' => 'Panel Financiero', 'icon' => 'fa-euro-sign', 'route' => 'admin.financial.dashboard'],
            ['label' => 'Usuarios', 'icon' => 'fa-users', 'route' => 'admin.users.enhanced'],
            ['label' => 'Gestión de Uploads', 'icon' => 'fa-file-upload', 'route' => 'admin.uploads'],
            // ['label' => 'Análisis de Uso', 'icon' => 'fa-chart-bar', 'route' => 'admin.usage.analytics'],
            ['label' => 'Monitoreo de Trabajos', 'icon' => 'fa-tasks', 'route' => 'admin.job.monitor'],
            // ['label' => 'Análisis de Créditos', 'icon' => 'fa-coins', 'route' => 'admin.credit.analytics'],
            ['label' => 'Configuración Stripe', 'icon' => 'fa-stripe-s', 'route' => 'admin.stripe.config'],
        ];
    }

    public function render()
    {
        return view('livewire.panel.sidebar');
    }
}


