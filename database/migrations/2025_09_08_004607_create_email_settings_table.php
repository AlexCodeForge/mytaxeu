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
        Schema::create('email_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->index(); // e.g., 'subscription_emails_enabled'
            $table->string('category')->index(); // e.g., 'features', 'notifications', 'admin'
            $table->string('subcategory')->nullable()->index(); // e.g., 'subscription_payment_confirmation'
            $table->text('value')->nullable(); // The actual setting value
            $table->string('type')->default('boolean'); // boolean, string, integer, array, email
            $table->string('label'); // Human readable label for admin panel
            $table->text('description')->nullable(); // Description for admin panel
            $table->json('options')->nullable(); // For dropdowns, validation rules, etc.
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_settings');
    }
};
