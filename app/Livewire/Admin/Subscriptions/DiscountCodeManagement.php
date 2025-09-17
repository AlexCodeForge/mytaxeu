<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Subscriptions;

use App\Models\DiscountCode;
use App\Models\SubscriptionPlan;
use App\Services\StripeDiscountService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class DiscountCodeManagement extends Component
{
    use WithPagination;

    // Form properties
    public ?DiscountCode $selectedCode = null;
    public bool $showModal = false;
    public bool $editMode = false;
    public bool $loading = false;

    // Form data
    public string $code = '';
    public string $name = '';
    public string $description = '';
    public string $type = 'percentage';
    public float $value = 0;
    public ?int $maxUses = null;
    public ?string $expiresAt = null;
    public bool $isActive = true;
    public bool $isGlobal = false;
    public array $selectedPlans = [];

    // Filters
    public string $search = '';
    public string $statusFilter = 'all';
    public string $typeFilter = 'all';

    // Available options
    public Collection $availablePlans;

    protected $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        $this->loadAvailablePlans();
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:discount_codes,code,' . ($this->selectedCode?->id ?? 'NULL')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'in:percentage,fixed'],
            'value' => ['required', 'numeric', 'min:0', function ($attribute, $value, $fail) {
                if ($this->type === 'percentage' && $value > 100) {
                    $fail('El porcentaje no puede ser mayor al 100%');
                }
                if ($this->type === 'fixed' && $value > 9999.99) {
                    $fail('El monto fijo no puede ser mayor a €9,999.99');
                }
            }],
            'maxUses' => ['nullable', 'integer', 'min:1'],
            'expiresAt' => ['nullable', 'date', 'after:now'],
            'isActive' => ['boolean'],
            'isGlobal' => ['boolean'],
            'selectedPlans' => ['required_if:isGlobal,false', 'array'],
            'selectedPlans.*' => ['exists:subscription_plans,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código es obligatorio',
            'code.unique' => 'Este código ya existe',
            'code.alpha_dash' => 'El código solo puede contener letras, números, guiones y guiones bajos',
            'name.required' => 'El nombre es obligatorio',
            'type.required' => 'El tipo de descuento es obligatorio',
            'value.required' => 'El valor del descuento es obligatorio',
            'value.min' => 'El valor debe ser mayor a 0',
            'selectedPlans.required_if' => 'Debe seleccionar al menos un plan si no es global',
            'expiresAt.after' => 'La fecha de expiración debe ser futura',
        ];
    }

    public function render()
    {
        $codes = DiscountCode::query()
            ->with(['subscriptionPlans', 'usages'])
            ->withCount('usages')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('code', 'like', '%' . $this->search . '%')
                      ->orWhere('name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                switch ($this->statusFilter) {
                    case 'active':
                        $query->where('is_active', true);
                        break;
                    case 'inactive':
                        $query->where('is_active', false);
                        break;
                    case 'expired':
                        $query->where('expires_at', '<', now());
                        break;
                    case 'valid':
                        $query->valid();
                        break;
                }
            })
            ->when($this->typeFilter !== 'all', function ($query) {
                $query->where('type', $this->typeFilter);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('livewire.admin.subscriptions.discount-code-management', [
            'codes' => $codes,
            'availablePlans' => $this->availablePlans,
        ])->layout('layouts.panel');
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function openEditModal(int $codeId): void
    {
        $this->selectedCode = DiscountCode::with('subscriptionPlans')->findOrFail($codeId);
        $this->fillForm();
        $this->editMode = true;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->loading = true;

        try {
            $this->validate();

            DB::transaction(function () {
                if ($this->editMode) {
                    $this->updateDiscountCode();
                } else {
                    $this->createDiscountCode();
                }
            });

            $this->closeModal();
            session()->flash('success', $this->editMode ? 'Código de descuento actualizado exitosamente' : 'Código de descuento creado exitosamente');

        } catch (\Exception $e) {
            Log::error('Error saving discount code', [
                'error' => $e->getMessage(),
                'code' => $this->code,
                'edit_mode' => $this->editMode,
            ]);
            session()->flash('error', 'Error al guardar el código de descuento: ' . $e->getMessage());
        } finally {
            $this->loading = false;
        }
    }

    public function toggleStatus(int $codeId): void
    {
        try {
            $code = DiscountCode::findOrFail($codeId);
            $code->update(['is_active' => !$code->is_active]);

            session()->flash('success', 'Estado del código actualizado exitosamente');
        } catch (\Exception $e) {
            session()->flash('error', 'Error al actualizar el estado del código');
        }
    }

    public function deleteCode(int $codeId): void
    {
        try {
            DB::transaction(function () use ($codeId) {
                $code = DiscountCode::findOrFail($codeId);

                // If it has a Stripe coupon, archive it
                if ($code->stripe_coupon_id) {
                    $stripeService = app(StripeDiscountService::class);
                    $stripeService->archiveCoupon($code->stripe_coupon_id);
                }

                $code->delete();
            });

            session()->flash('success', 'Código de descuento eliminado exitosamente');
        } catch (\Exception $e) {
            Log::error('Error deleting discount code', [
                'error' => $e->getMessage(),
                'code_id' => $codeId,
            ]);
            session()->flash('error', 'Error al eliminar el código de descuento');
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
        $this->resetValidation();
    }

    #[On('refresh-codes')]
    public function refreshCodes(): void
    {
        // This will trigger a re-render
    }

    private function loadAvailablePlans(): void
    {
        $this->availablePlans = SubscriptionPlan::active()->ordered()->get();
    }

    private function resetForm(): void
    {
        $this->selectedCode = null;
        $this->code = '';
        $this->name = '';
        $this->description = '';
        $this->type = 'percentage';
        $this->value = 0;
        $this->maxUses = null;
        $this->expiresAt = null;
        $this->isActive = true;
        $this->isGlobal = false;
        $this->selectedPlans = [];
    }

    private function fillForm(): void
    {
        if (!$this->selectedCode) {
            return;
        }

        $this->code = $this->selectedCode->code;
        $this->name = $this->selectedCode->name;
        $this->description = $this->selectedCode->description ?? '';
        $this->type = $this->selectedCode->type;
        $this->value = (float) $this->selectedCode->value;
        $this->maxUses = $this->selectedCode->max_uses;
        $this->expiresAt = $this->selectedCode->expires_at?->format('Y-m-d\TH:i');
        $this->isActive = $this->selectedCode->is_active;
        $this->isGlobal = $this->selectedCode->is_global;
        $this->selectedPlans = $this->selectedCode->subscriptionPlans->pluck('id')->toArray();
    }

    private function createDiscountCode(): void
    {
        $stripeService = app(StripeDiscountService::class);

        // Create Stripe coupon first
        $value = (float) $this->value;
        $stripeCouponId = $stripeService->createCoupon([
            'id' => $this->code,
            'name' => $this->name,
            'duration' => $this->expiresAt ? 'once' : 'forever',
            'amount_off' => $this->type === 'fixed' ? (int)($value * 100) : null,
            'percent_off' => $this->type === 'percentage' ? $value : null,
            'max_redemptions' => $this->maxUses,
            'redeem_by' => $this->expiresAt ? strtotime($this->expiresAt) : null,
        ]);

        $discountCode = DiscountCode::create([
            'code' => strtoupper($this->code),
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'value' => (float) $this->value,
            'max_uses' => $this->maxUses,
            'expires_at' => $this->expiresAt ? now()->parse($this->expiresAt) : null,
            'is_active' => $this->isActive,
            'is_global' => $this->isGlobal,
            'stripe_coupon_id' => $stripeCouponId,
        ]);

        if (!$this->isGlobal && !empty($this->selectedPlans)) {
            $discountCode->subscriptionPlans()->attach($this->selectedPlans);
        }
    }

    private function updateDiscountCode(): void
    {
        if (!$this->selectedCode) {
            return;
        }

        $this->selectedCode->update([
            'code' => strtoupper($this->code),
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'value' => (float) $this->value,
            'max_uses' => $this->maxUses,
            'expires_at' => $this->expiresAt ? now()->parse($this->expiresAt) : null,
            'is_active' => $this->isActive,
            'is_global' => $this->isGlobal,
        ]);

        // Update plan associations
        if ($this->isGlobal) {
            $this->selectedCode->subscriptionPlans()->detach();
        } else {
            $this->selectedCode->subscriptionPlans()->sync($this->selectedPlans);
        }

        // Update Stripe coupon if needed
        if ($this->selectedCode->stripe_coupon_id) {
            $stripeService = app(StripeDiscountService::class);
            $stripeService->updateCoupon($this->selectedCode->stripe_coupon_id, [
                'name' => $this->name,
            ]);
        }
    }
}
