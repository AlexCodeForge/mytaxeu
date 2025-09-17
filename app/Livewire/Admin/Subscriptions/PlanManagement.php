<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Subscriptions;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class PlanManagement extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?SubscriptionPlan $editingPlan = null;

    // Form fields
    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public ?float $monthlyPrice = null;
    public array $features = [];
    public ?int $maxAlertsPerMonth = null;
    public bool $isActive = true;
    public bool $isFeatured = false;

    protected array $rules = [
        'name' => 'required|string|max:255',
        'slug' => 'required|string|max:255|alpha_dash',
        'description' => 'nullable|string',
        'monthlyPrice' => 'nullable|numeric|min:0',
        'features' => 'array',
        'features.*' => 'string',
        'maxAlertsPerMonth' => 'nullable|integer|min:0',
        'isActive' => 'boolean',
        'isFeatured' => 'boolean',
    ];

    public function mount(): void
    {
        $this->authorize('viewAny', SubscriptionPlan::class);
    }

    #[Layout('layouts.panel')]
    public function render(): View
    {
        $plans = SubscriptionPlan::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('slug', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('is_active', $this->statusFilter === 'active');
            })
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->paginate(10);

        return view('livewire.admin.subscriptions.plan-management', [
            'plans' => $plans,
        ]);
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', SubscriptionPlan::class);
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function openEditModal(SubscriptionPlan $plan): void
    {
        $this->authorize('update', $plan);
        $this->editingPlan = $plan;
        $this->fillForm($plan);
        $this->showEditModal = true;
    }

    public function closeModals(): void
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->editingPlan = null;
        $this->resetForm();
        $this->resetValidation();
    }

    public function createPlan(): void
    {
        $this->authorize('create', SubscriptionPlan::class);
        $this->validate();

        // Check slug uniqueness
        if (SubscriptionPlan::where('slug', $this->slug)->exists()) {
            $this->addError('slug', 'El slug ya existe.');
            return;
        }

        SubscriptionPlan::create([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'monthly_price' => $this->monthlyPrice,
            'is_monthly_enabled' => true,
            'features' => array_filter($this->features),
            'max_alerts_per_month' => $this->maxAlertsPerMonth,
            'is_active' => $this->isActive,
            'is_featured' => $this->isFeatured,
            'sort_order' => SubscriptionPlan::max('sort_order') + 1,
        ]);

        $this->dispatch('plan-created');
        $this->closeModals();
        session()->flash('message', 'Plan creado exitosamente.');
    }

    public function updatePlan(): void
    {
        $this->authorize('update', $this->editingPlan);
        $this->validate();

        // Check slug uniqueness except for current plan
        if (SubscriptionPlan::where('slug', $this->slug)->where('id', '!=', $this->editingPlan->id)->exists()) {
            $this->addError('slug', 'El slug ya existe.');
            return;
        }

        $this->editingPlan->update([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'monthly_price' => $this->monthlyPrice,
            'features' => array_filter($this->features),
            'max_alerts_per_month' => $this->maxAlertsPerMonth,
            'is_active' => $this->isActive,
            'is_featured' => $this->isFeatured,
        ]);

        $this->dispatch('plan-updated');
        $this->closeModals();
        session()->flash('message', 'Plan actualizado exitosamente.');
    }

    public function togglePlanStatus(SubscriptionPlan $plan): void
    {
        $this->authorize('update', $plan);
        $plan->update(['is_active' => !$plan->is_active]);

        $status = $plan->is_active ? 'activado' : 'desactivado';
        session()->flash('message', "Plan {$status} exitosamente.");
    }

    public function deletePlan(SubscriptionPlan $plan): void
    {
        $this->authorize('delete', $plan);

        // Check if plan has active subscriptions
        if ($plan->subscriptions()->where('stripe_status', 'active')->exists()) {
            session()->flash('error', 'No se puede eliminar un plan con suscripciones activas.');
            return;
        }

        $plan->delete();
        session()->flash('message', 'Plan eliminado exitosamente.');
    }

    public function addFeature(): void
    {
        $this->features[] = '';
    }

    public function removeFeature(int $index): void
    {
        unset($this->features[$index]);
        $this->features = array_values($this->features);
    }

    protected function resetForm(): void
    {
        $this->name = '';
        $this->slug = '';
        $this->description = '';
        $this->monthlyPrice = null;
        $this->features = [];
        $this->maxAlertsPerMonth = null;
        $this->isActive = true;
        $this->isFeatured = false;
    }

    protected function fillForm(SubscriptionPlan $plan): void
    {
        $this->name = $plan->name;
        $this->slug = $plan->slug;
        $this->description = $plan->description ?? '';
        $this->monthlyPrice = $plan->monthly_price ? (float) $plan->monthly_price : null;
        $this->features = $plan->features ?? [];
        $this->maxAlertsPerMonth = $plan->max_alerts_per_month;
        $this->isActive = $plan->is_active;
        $this->isFeatured = $plan->is_featured;
    }

    /**
     * Handle plan order updates from drag and drop
     */
    public function updatePlanOrder(array $sortedItems): void
    {
        if (!auth()->user() || !auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        foreach ($sortedItems as $item) {
            SubscriptionPlan::where('id', $item['value'])
                ->update(['sort_order' => $item['order']]);
        }

        session()->flash('message', 'Orden de planes actualizado exitosamente.');
    }

    /**
     * Handle feature order updates from drag and drop within modal
     */
    public function updateFeatureOrder(array $sortedItems): void
    {
        $reorderedFeatures = [];

        foreach ($sortedItems as $item) {
            $index = $item['value'];
            if (isset($this->features[$index])) {
                $reorderedFeatures[] = $this->features[$index];
            }
        }

        $this->features = $reorderedFeatures;
    }

    public function updatedName(): void
    {
        if (empty($this->slug)) {
            $this->slug = \Str::slug($this->name);
        }
    }
}
