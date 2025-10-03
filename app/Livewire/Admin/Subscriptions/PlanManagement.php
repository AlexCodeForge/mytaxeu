<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Subscriptions;

use App\Models\SubscriptionPlan;
use App\Services\StripeConfigurationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Stripe\Price;
use Stripe\Product;
use Stripe\Stripe;

class PlanManagement extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public ?SubscriptionPlan $editingPlan = null;
    public bool $syncing = false;

    // Form fields
    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public ?float $monthlyPrice = null;
    public int $minimumCommitmentMonths = 3;
    public array $features = [];
    public ?int $maxAlertsPerMonth = null;
    public bool $isActive = true;
    public bool $isFeatured = false;

    protected array $rules = [
        'name' => 'required|string|max:255',
        'slug' => 'required|string|max:255|alpha_dash',
        'description' => 'nullable|string',
        'monthlyPrice' => 'nullable|numeric|min:0',
        'minimumCommitmentMonths' => 'required|integer|min:3',
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

        $plan = SubscriptionPlan::create([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'monthly_price' => $this->monthlyPrice,
            'minimum_commitment_months' => $this->minimumCommitmentMonths,
            'is_monthly_enabled' => true,
            'features' => array_filter($this->features),
            'max_alerts_per_month' => $this->maxAlertsPerMonth,
            'is_active' => $this->isActive,
            'is_featured' => $this->isFeatured,
            'sort_order' => SubscriptionPlan::max('sort_order') + 1,
        ]);

        Log::info('✅ Plan created with minimum commitment', [
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'commitment_months' => $this->minimumCommitmentMonths,
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
            'minimum_commitment_months' => $this->minimumCommitmentMonths,
            'features' => array_filter($this->features),
            'max_alerts_per_month' => $this->maxAlertsPerMonth,
            'is_active' => $this->isActive,
            'is_featured' => $this->isFeatured,
        ]);

        Log::info('✅ Plan updated with minimum commitment', [
            'plan_id' => $this->editingPlan->id,
            'plan_name' => $this->editingPlan->name,
            'commitment_months' => $this->minimumCommitmentMonths,
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
        $this->minimumCommitmentMonths = 3; // Reset to default minimum
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
        $this->minimumCommitmentMonths = $plan->minimum_commitment_months ?? 3;
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

    /**
     * Sync subscription plans with Stripe
     */
    public function syncWithStripe(): void
    {
        $this->authorize('create', SubscriptionPlan::class);
        $this->syncing = true;

        try {
            // Check if Stripe is configured
            $stripeService = app(StripeConfigurationService::class);
            if (!$stripeService->isConfigured()) {
                session()->flash('error', 'Stripe no está configurado. Configure Stripe primero.');
                return;
            }

            $stripeConfig = $stripeService->getConfig();
            Stripe::setApiKey($stripeConfig['secret_key']);

            $syncedCount = 0;
            $updatedCount = 0;

            DB::transaction(function () use (&$syncedCount, &$updatedCount) {
                // Get all local plans
                $localPlans = SubscriptionPlan::all();

                foreach ($localPlans as $plan) {
                    if (!$plan->monthly_price || $plan->monthly_price <= 0) {
                        continue; // Skip free plans
                    }

                    try {
                        // Create or update Stripe product
                        $stripeProduct = $this->createOrUpdateStripeProduct($plan);

                        // Create or update Stripe price for monthly billing
                        $stripePrice = $this->createOrUpdateStripePrice($plan, $stripeProduct);

                        // Update local plan with Stripe IDs
                        $wasAlreadySynced = !empty($plan->stripe_monthly_price_id);
                        $updated = $plan->update([
                            'stripe_monthly_price_id' => $stripePrice->id,
                        ]);

                        if ($updated) {
                            $wasAlreadySynced ? $updatedCount++ : $syncedCount++;
                        }

                        Log::info('Plan synced with Stripe', [
                            'plan_id' => $plan->id,
                            'plan_name' => $plan->name,
                            'stripe_product_id' => $stripeProduct->id,
                            'stripe_price_id' => $stripePrice->id,
                        ]);

                    } catch (\Exception $e) {
                        Log::error('Error syncing plan with Stripe', [
                            'plan_id' => $plan->id,
                            'plan_name' => $plan->name,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue with other plans
                    }
                }
            });

            $message = "Sincronización completada: {$syncedCount} planes nuevos, {$updatedCount} planes actualizados";
            session()->flash('message', $message);

            Log::info('Stripe plans sync completed', [
                'synced_count' => $syncedCount,
                'updated_count' => $updatedCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Error during Stripe sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            session()->flash('error', 'Error durante la sincronización: ' . $e->getMessage());
        } finally {
            $this->syncing = false;
        }
    }

    /**
     * Create or update a Stripe product for a plan
     */
    private function createOrUpdateStripeProduct(SubscriptionPlan $plan): Product
    {
        try {
            // Try to find existing product by metadata
            $products = Product::all(['limit' => 100]);
            $existingProduct = null;

            foreach ($products->data as $product) {
                if (isset($product->metadata['plan_slug']) && $product->metadata['plan_slug'] === $plan->slug) {
                    $existingProduct = $product;
                    break;
                }
            }

            if ($existingProduct) {
                // Update existing product
                $existingProduct->name = $plan->name;
                $existingProduct->description = $plan->description ?? '';
                $existingProduct->metadata = [
                    'plan_id' => (string) $plan->id,
                    'plan_slug' => $plan->slug,
                ];
                $existingProduct->save();
                return $existingProduct;
            } else {
                // Create new product
                return Product::create([
                    'name' => $plan->name,
                    'description' => $plan->description ?? '',
                    'metadata' => [
                        'plan_id' => (string) $plan->id,
                        'plan_slug' => $plan->slug,
                    ],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error creating/updating Stripe product', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Create or update a Stripe price for a plan
     */
    private function createOrUpdateStripePrice(SubscriptionPlan $plan, Product $stripeProduct): Price
    {
        try {
            // Check if we already have a price ID and if it's still valid
            if ($plan->stripe_monthly_price_id) {
                try {
                    $existingPrice = Price::retrieve($plan->stripe_monthly_price_id);

                    // Check if price amount matches
                    $expectedAmount = (int) ($plan->monthly_price * 100); // Convert to cents
                    if ($existingPrice->unit_amount === $expectedAmount && $existingPrice->active) {
                        return $existingPrice; // Price is still valid
                    }
                } catch (\Exception $e) {
                    // Price doesn't exist anymore, create a new one
                }
            }

            // Create new price
            return Price::create([
                'product' => $stripeProduct->id,
                'unit_amount' => (int) ($plan->monthly_price * 100), // Convert to cents
                'currency' => 'eur',
                'recurring' => [
                    'interval' => 'month',
                ],
                'metadata' => [
                    'plan_id' => (string) $plan->id,
                    'plan_slug' => $plan->slug,
                    'minimum_commitment_months' => (string) $plan->getMinimumCommitmentMonths(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating/updating Stripe price', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
