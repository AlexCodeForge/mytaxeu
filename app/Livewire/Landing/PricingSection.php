<?php

namespace App\Livewire\Landing;

use App\Models\SubscriptionPlan;
use Livewire\Component;

class PricingSection extends Component
{
    public function render()
    {
        $plans = SubscriptionPlan::active()->ordered()->get();

        return view('livewire.landing.pricing-section', [
            'plans' => $plans
        ]);
    }
}
