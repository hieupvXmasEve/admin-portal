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
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('created_by_user_id');
            $table->boolean('is_historical')->default(false)->after('is_manual');
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->after('is_historical');

            // Add index for manual events
            $table->index(['is_manual', 'is_historical']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['created_by_admin_id']);
            $table->dropIndex(['is_manual', 'is_historical']);
            $table->dropColumn(['is_manual', 'is_historical', 'created_by_admin_id']);
        });
    }
};
