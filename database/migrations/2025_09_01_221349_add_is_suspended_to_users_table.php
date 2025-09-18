<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_suspended')->default(false)->after('is_admin');
            $table->timestamp('suspended_at')->nullable()->after('is_suspended');
            $table->foreignId('suspended_by')->nullable()->constrained('users')->onDelete('set null')->after('suspended_at');
            $table->text('suspension_reason')->nullable()->after('suspended_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['suspended_by']);
            $table->dropColumn(['is_suspended', 'suspended_at', 'suspended_by', 'suspension_reason']);
        });
    }
};
