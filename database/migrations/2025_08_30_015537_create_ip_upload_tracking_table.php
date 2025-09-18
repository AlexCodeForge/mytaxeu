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
        Schema::create('ip_upload_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->unsignedInteger('upload_count')->default(0);
            $table->unsignedInteger('total_lines_attempted')->default(0);
            $table->timestamp('last_upload_at')->useCurrent();
            $table->timestamps();
            
            $table->index('last_upload_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_upload_tracking');
    }
};
