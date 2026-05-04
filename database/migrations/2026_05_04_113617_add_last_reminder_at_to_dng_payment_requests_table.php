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
        Schema::table('dng_payment_requests', function (Blueprint $table) {
            $table->timestamp('last_reminder_at')->nullable()->after('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dng_payment_requests', function (Blueprint $table) {
            $table->dropColumn('last_reminder_at');
        });
    }
};
