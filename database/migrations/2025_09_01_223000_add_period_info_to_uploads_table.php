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
        Schema::table('uploads', function (Blueprint $table) {
            $table->json('detected_periods')->nullable()->after('csv_line_count');
            $table->integer('period_count')->default(0)->after('detected_periods');
            $table->integer('credits_required')->default(1)->after('period_count');
            $table->integer('credits_consumed')->default(0)->after('credits_required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->dropColumn(['detected_periods', 'period_count', 'credits_required', 'credits_consumed']);
        });
    }
};
