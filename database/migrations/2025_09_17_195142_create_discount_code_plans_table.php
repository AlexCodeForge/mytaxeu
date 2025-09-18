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
        Schema::create('discount_code_plans', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('discount_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();

            $table->timestamps();

            // Unique constraint to prevent duplicate entries
            $table->unique(['discount_code_id', 'subscription_plan_id'], 'unique_code_plan');

            // Indexes
            $table->index(['discount_code_id']);
            $table->index(['subscription_plan_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discount_code_plans');
    }
};
