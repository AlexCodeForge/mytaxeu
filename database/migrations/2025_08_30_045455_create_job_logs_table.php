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
        if (!Schema::hasTable('job_logs')) {
            Schema::create('job_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('job_id');
                $table->enum('level', ['info', 'warning', 'error'])->default('info');
                $table->text('message');
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['job_id']);
                $table->index(['level']);
                $table->index(['created_at']);

                $table->foreign('job_id')->references('id')->on('jobs')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_logs');
    }
};
