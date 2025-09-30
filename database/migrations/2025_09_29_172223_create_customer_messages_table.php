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
        Schema::create('customer_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('customer_conversations')->onDelete('cascade');
            $table->string('message_id')->unique()->nullable(); // Email Message-ID header
            $table->string('sender_email');
            $table->string('sender_name')->nullable();
            $table->enum('sender_type', ['customer', 'admin']);
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('subject', 500);
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('sent_at');
            $table->timestamps();

            // Indexes for performance
            $table->index(['conversation_id', 'sent_at']);
            $table->index(['sender_type', 'is_read']);
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_messages');
    }
};
