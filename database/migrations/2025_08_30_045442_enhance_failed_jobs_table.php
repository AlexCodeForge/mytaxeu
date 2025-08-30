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
        Schema::table('failed_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('failed_jobs', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable();
            }
            if (!Schema::hasColumn('failed_jobs', 'file_name')) {
                $table->string('file_name')->nullable();
            }
            if (!Schema::hasColumn('failed_jobs', 'retry_count')) {
                $table->integer('retry_count')->default(0);
            }
        });

        Schema::table('failed_jobs', function (Blueprint $table) {
            try {
                $table->index(['user_id']);
            } catch (\Exception $e) {
                // Index might already exist
            }
            try {
                $table->index(['failed_at']);
            } catch (\Exception $e) {
                // Index might already exist
            }
            try {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            } catch (\Exception $e) {
                // Foreign key might already exist
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['failed_at']);
            $table->dropIndex(['user_id']);
            $table->dropColumn(['user_id', 'file_name', 'retry_count']);
        });
    }
};
