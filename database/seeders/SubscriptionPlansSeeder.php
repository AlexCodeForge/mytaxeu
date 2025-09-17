<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionPlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Plan Gratuito',
                'slug' => 'free',
                'description' => 'Plan básico gratuito para comenzar',
                'monthly_price' => 0.00,
                'is_monthly_enabled' => true,
                'features' => [
                    'Límite básico de transacciones',
                    'Soporte por email básico'
                ],
                'max_alerts_per_month' => 10,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 0,
            ],
            [
                'name' => 'Plan Starter',
                'slug' => 'starter',
                'description' => 'Perfecto para comenzar con Amazon',
                'monthly_price' => 25.00,
                'is_monthly_enabled' => true,
                'features' => [
                    '1 cliente Amazon/mes',
                    'Informes automáticos',
                    'Soporte por email'
                ],
                'max_alerts_per_month' => 1,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Plan Business',
                'slug' => 'business',
                'description' => 'Ideal para empresas en crecimiento',
                'monthly_price' => 125.00,
                'is_monthly_enabled' => true,
                'features' => [
                    '5 clientes Amazon/mes',
                    'Todo del plan anterior',
                    'Soporte prioritario',
                    'Ahorro: €3000/mes'
                ],
                'max_alerts_per_month' => 5,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Plan Enterprise',
                'slug' => 'enterprise',
                'description' => 'Solución completa para grandes empresas',
                'monthly_price' => 500.00,
                'is_monthly_enabled' => true,
                'features' => [
                    '20 clientes Amazon/mes',
                    'Todo de planes anteriores',
                    'Soporte dedicado',
                    'Ahorro: €12000/mes'
                ],
                'max_alerts_per_month' => 20,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }

        $this->command->info('Subscription plans seeded successfully!');
    }
}
