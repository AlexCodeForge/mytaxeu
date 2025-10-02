<?php

namespace App\Livewire\Landing;

use App\Models\SubscriptionPlan;
use Livewire\Component;

class PricingSection extends Component
{
    public function render()
    {
        $plans = SubscriptionPlan::active()->ordered()->get();

        \Log::info('🔍 Pricing Section Plans Loaded', [
            'count' => $plans->count(),
            'slugs' => $plans->pluck('slug')->toArray(),
            'free_plan_exists' => $plans->where('slug', 'free')->first() ? 'yes' : 'no'
        ]);

        return view('livewire.landing.pricing-section', [
            'plans' => $plans
        ]);
    }
}
