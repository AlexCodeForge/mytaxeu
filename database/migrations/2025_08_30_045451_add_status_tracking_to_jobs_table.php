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
        Schema::table('jobs', function (Blueprint $table) {
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])
                  ->default('queued');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('file_name')->nullable();
            $table->text('error_message')->nullable();

            $table->index(['status']);
            $table->index(['user_id']);
            $table->index(['created_at']);
            $table->index(['user_id', 'status']);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'started_at',
                'completed_at',
                'user_id',
                'file_name',
                'error_message'
            ]);
        });
    }
};
