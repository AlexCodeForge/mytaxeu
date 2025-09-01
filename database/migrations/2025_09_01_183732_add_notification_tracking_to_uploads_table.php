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
        Schema::table('uploads', function (Blueprint $table): void {
            $table->timestamp('notification_sent_at')->nullable()->after('status');
            $table->enum('notification_type', ['success', 'failure', 'processing'])->nullable()->after('notification_sent_at');

            $table->index(['notification_sent_at', 'notification_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table): void {
            $table->dropIndex(['notification_sent_at', 'notification_type']);
            $table->dropColumn(['notification_sent_at', 'notification_type']);
        });
    }
};
