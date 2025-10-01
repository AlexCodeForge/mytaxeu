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
            ['label' => 'Gestión de Tarifas', 'icon' => 'fa-exchange-alt', 'route' => 'admin.rate.management'],
            ['label' => 'Planes de Suscripción', 'icon' => 'fa-credit-card', 'route' => 'admin.subscriptions.plans'],
            ['label' => 'Códigos de Descuento', 'icon' => 'fa-tags', 'route' => 'admin.subscriptions.discount-codes'],
            // ['label' => 'Análisis de Uso', 'icon' => 'fa-chart-bar', 'route' => 'admin.usage.analytics'],
            ['label' => 'Monitoreo de Trabajos', 'icon' => 'fa-tasks', 'route' => 'admin.job.monitor'],
            // ['label' => 'Análisis de Créditos', 'icon' => 'fa-coins', 'route' => 'admin.credit.analytics'],
            ['label' => 'Emails de Clientes', 'icon' => 'fa-envelope', 'route' => 'admin.customer-emails.index'],
            [
                'label' => 'Configuración Stripe',
                'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2zm6.226 5.385c-.584 0-.937.164-.937.593 0 .468.607.674 1.36.93 1.228.415 2.844.963 2.851 2.993C11.5 11.868 9.924 13 7.63 13a7.7 7.7 0 0 1-3.009-.626V9.758c.926.506 2.095.88 3.01.88.617 0 1.058-.165 1.058-.671 0-.518-.658-.755-1.453-1.041C6.026 8.49 4.5 7.94 4.5 6.11 4.5 4.165 5.988 3 8.226 3a7.3 7.3 0 0 1 2.734.505v2.583c-.838-.45-1.896-.703-2.734-.703"></path></svg>',
                'route' => 'admin.stripe.config',
                'isSvg' => true
            ],
            ['label' => 'Configuración de Emails', 'icon' => 'fa-envelope-open-text', 'route' => 'admin.email-settings.index'],
        ];
    }

    public function isActiveRoute(string $route): bool
    {
        // Exact match
        if ($this->currentRoute === $route) {
            \Log::info('Sidebar: Exact route match', ['route' => $route, 'currentRoute' => $this->currentRoute]);
            return true;
        }

        // Handle email settings sub-routes
        if ($route === 'admin.email-settings.index' && str_starts_with($this->currentRoute, 'admin.email-settings.')) {
            \Log::info('Sidebar: Email settings sub-route match', ['route' => $route, 'currentRoute' => $this->currentRoute]);
            return true;
        }

        // Note: Subscription routes (admin.subscriptions.plans and admin.subscriptions.discount-codes)
        // use exact matching only - no sub-routes exist, so prefix matching is not needed

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


