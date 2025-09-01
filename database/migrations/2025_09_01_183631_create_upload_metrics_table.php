<?php

declare(strict_types=1);

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
        Schema::create('upload_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('upload_id');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size_bytes');
            $table->unsignedInteger('line_count');
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->unsignedInteger('processing_duration_seconds')->nullable();
            $table->unsignedInteger('credits_consumed')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['user_id', 'created_at']);
            $table->index('status');
            $table->index('processing_completed_at');
            $table->index('line_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_metrics');
    }
};
