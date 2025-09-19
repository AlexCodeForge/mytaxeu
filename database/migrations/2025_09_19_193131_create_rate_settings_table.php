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
        Schema::create('rate_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['exchange_rate', 'vat_rate'])->index();
            $table->string('currency', 3)->nullable()->index(); // PLN, EUR, SEK, GBP for exchange rates
            $table->string('country', 2)->nullable()->index(); // AT, BE, etc. for VAT rates
            $table->decimal('rate', 10, 6); // Rate value (e.g., 0.319033 for PLN, 0.21 for VAT)
            $table->date('effective_date')->index(); // When this rate becomes active
            $table->enum('source', ['manual', 'api_vatcomply', 'api_ecb', 'api_other'])->default('manual');
            $table->enum('update_mode', ['manual', 'automatic'])->default('manual');
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable(); // Additional data from API responses
            $table->timestamp('last_updated_at')->nullable(); // When last fetched from API
            $table->timestamps();

            // Indexes for performance
            $table->index(['type', 'is_active']);
            $table->index(['type', 'currency', 'is_active']);
            $table->index(['type', 'country', 'is_active']);
            $table->index(['effective_date', 'is_active']);

            // Unique constraints
            $table->unique(['type', 'currency', 'effective_date'], 'unique_exchange_rate');
            $table->unique(['type', 'country', 'effective_date'], 'unique_vat_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_settings');
    }
};
