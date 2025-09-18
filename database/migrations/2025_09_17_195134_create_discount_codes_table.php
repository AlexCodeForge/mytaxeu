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
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();

            // Basic discount code information
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Discount type and value
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 8, 2); // Percentage (0-100) or fixed amount

            // Usage limits and tracking
            $table->integer('max_uses')->nullable(); // null = unlimited
            $table->integer('used_count')->default(0);

            // Validity and activation
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_global')->default(false); // If true, applies to all plans

            // Stripe integration
            $table->string('stripe_coupon_id')->nullable()->unique();

            // Additional metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['is_active', 'expires_at']);
            $table->index(['code', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};
