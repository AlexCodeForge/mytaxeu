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
                'minimum_commitment_months' => 3,
                'is_monthly_enabled' => true,
                'features' => [
                    'Hasta 100 transacciones/mes',
                    'Informes automáticos',
                    'Soporte por email'
                ],
                'max_alerts_per_month' => 100,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 0,
            ],
            [
                'name' => 'Seller Individual',
                'slug' => 'starter',
                'description' => 'Perfecto para vendedores individuales de Amazon',
                'monthly_price' => 25.00,
                'minimum_commitment_months' => 3,
                'is_monthly_enabled' => true,
                'features' => [
                    'Transacciones ilimitadas',
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
                'name' => 'Plan Gestoría',
                'slug' => 'business',
                'description' => 'Ideal para gestorías y asesores fiscales',
                'monthly_price' => 100.00,
                'minimum_commitment_months' => 3,
                'is_monthly_enabled' => true,
                'features' => [
                    'Transacciones ilimitadas',
                    '5 clientes o vendedores Amazon/mes',
                    'Todo del plan anterior',
                    'Soporte prioritario'
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
                'monthly_price' => 400.00,
                'minimum_commitment_months' => 3,
                'is_monthly_enabled' => true,
                'features' => [
                    'Transacciones ilimitadas',
                    '20 clientes Amazon/mes',
                    'Todo de planes anteriores',
                    'Soporte dedicado'
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
