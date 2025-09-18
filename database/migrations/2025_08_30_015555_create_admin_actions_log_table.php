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
        Schema::create('admin_actions_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->constrained('users')->onDelete('restrict');
            $table->enum('action_type', ['limit_override', 'limit_reset', 'usage_reset']);
            $table->foreignId('target_user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('target_ip_address', 45)->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['admin_user_id', 'created_at']);
            $table->index('target_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_actions_log');
    }
};
