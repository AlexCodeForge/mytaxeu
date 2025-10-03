<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Add minimum commitment months field with default of 3
            $table->unsignedInteger('minimum_commitment_months')
                ->default(3)
                ->after('monthly_price')
                ->comment('Minimum commitment period in months (default: 3)');

            // Add index for performance
            $table->index('minimum_commitment_months');
        });

        Log::info('✅ Migration: Added minimum_commitment_months field to subscription_plans table', [
            'table' => 'subscription_plans',
            'field' => 'minimum_commitment_months',
            'default_value' => 3
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropIndex(['minimum_commitment_months']);
            $table->dropColumn('minimum_commitment_months');
        });

        Log::info('🔄 Migration: Rolled back minimum_commitment_months field from subscription_plans table');
    }
};
