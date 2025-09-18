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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('total_lines_processed')->default(0)->after('credits');
            $table->unsignedInteger('current_month_usage')->default(0)->after('total_lines_processed');
            $table->date('usage_reset_date')->nullable()->after('current_month_usage');

            $table->index(['current_month_usage', 'usage_reset_date'], 'idx_usage_stats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_usage_stats');
            $table->dropColumn(['total_lines_processed', 'current_month_usage', 'usage_reset_date']);
        });
    }
};
