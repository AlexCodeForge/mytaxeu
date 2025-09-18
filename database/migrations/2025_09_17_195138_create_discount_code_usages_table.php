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
        Schema::create('discount_code_usages', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('discount_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();

            // Pricing information
            $table->decimal('original_amount', 8, 2);
            $table->decimal('discount_amount', 8, 2);
            $table->decimal('final_amount', 8, 2);

            // Stripe integration
            $table->string('stripe_coupon_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();

            // Additional metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Ensure one use per user per code
            $table->unique(['discount_code_id', 'user_id'], 'unique_user_code_usage');

            // Indexes
            $table->index(['user_id', 'created_at']);
            $table->index(['discount_code_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_code_usages');
    }
};
