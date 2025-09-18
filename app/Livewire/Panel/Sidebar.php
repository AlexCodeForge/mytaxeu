<?php

declare(strict_types=1);

namespace App\Livewire\Panel;

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Sidebar extends Component
{
    public array $userLinks = [];
    public array $adminLinks = [];
    public bool $isAdmin = false;
    public string $currentRoute = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->isAdmin = $user && method_exists($user, 'isAdmin') && $user->isAdmin();
        $this->currentRoute = request()->route()->getName() ?? '';

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
            ['label' => 'Planes de Suscripción', 'icon' => 'fa-credit-card', 'route' => 'admin.subscriptions.plans'],
            ['label' => 'Códigos de Descuento', 'icon' => 'fa-tags', 'route' => 'admin.subscriptions.discount-codes'],
            // ['label' => 'Análisis de Uso', 'icon' => 'fa-chart-bar', 'route' => 'admin.usage.analytics'],
            ['label' => 'Monitoreo de Trabajos', 'icon' => 'fa-tasks', 'route' => 'admin.job.monitor'],
            // ['label' => 'Análisis de Créditos', 'icon' => 'fa-coins', 'route' => 'admin.credit.analytics'],
            ['label' => 'Configuración Stripe', 'icon' => 'fa-stripe-s', 'route' => 'admin.stripe.config'],
            ['label' => 'Configuración de Emails', 'icon' => 'fa-envelope-open-text', 'route' => 'admin.email-settings.index'],
        ];
    }

    public function isActiveRoute(string $route): bool
    {
        // Exact match
        if ($this->currentRoute === $route) {
            return true;
        }

        // Handle email settings sub-routes
        if ($route === 'admin.email-settings.index' && str_starts_with($this->currentRoute, 'admin.email-settings.')) {
            return true;
        }

        // Handle subscription sub-routes
        if ($route === 'admin.subscriptions.plans' && str_starts_with($this->currentRoute, 'admin.subscriptions.')) {
            return true;
        }
        if ($route === 'admin.subscriptions.discount-codes' && str_starts_with($this->currentRoute, 'admin.subscriptions.')) {
            return true;
        }

        return false;
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }

    public function render()
    {
        return view('livewire.panel.sidebar');
    }
}


