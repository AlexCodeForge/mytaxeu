<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();

            // Basic plan information
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Pricing for different frequencies
            $table->decimal('weekly_price', 8, 2)->nullable();
            $table->decimal('monthly_price', 8, 2)->nullable();
            $table->decimal('yearly_price', 8, 2)->nullable();

            // Stripe price IDs for each frequency
            $table->string('stripe_weekly_price_id')->nullable();
            $table->string('stripe_monthly_price_id')->nullable();
            $table->string('stripe_yearly_price_id')->nullable();

            // Frequency toggles
            $table->boolean('is_weekly_enabled')->default(false);
            $table->boolean('is_monthly_enabled')->default(true);
            $table->boolean('is_yearly_enabled')->default(false);

            // Discount percentages per frequency
            $table->decimal('weekly_discount_percentage', 5, 2)->nullable();
            $table->decimal('monthly_discount_percentage', 5, 2)->nullable();
            $table->decimal('yearly_discount_percentage', 5, 2)->nullable();

            // Plan features and limits
            $table->json('features')->nullable();
            $table->integer('max_alerts_per_month')->nullable();
            $table->integer('max_courses')->nullable();

            // Premium access flags
            $table->boolean('premium_chat_access')->default(false);
            $table->boolean('premium_events_access')->default(false);
            $table->boolean('advanced_analytics')->default(false);
            $table->boolean('priority_support')->default(false);

            // Plan management
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('is_active');
            $table->index('sort_order');
            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
