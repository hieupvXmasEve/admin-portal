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
        Schema::create('billing_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('due_date');
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->timestamps();

            $table->index('semester_id');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });

        // Note: Foreign key for billing_cycle_id in voucher_redemptions will be added
        // in migration 2025_10_08_113436_add_foreign_keys_to_voucher_redemptions.php
        // after this migration completes
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback voucher_redemptions changes first
        if (Schema::hasTable('voucher_redemptions')) {
            Schema::table('voucher_redemptions', function (Blueprint $table) {
                $table->dropForeign(['billing_cycle_id']);
                $table->dropIndex(['billing_cycle_id']);
                $table->dropIndex(['status']);
                $table->dropColumn(['billing_cycle_id', 'status']);
            });
        }

        Schema::dropIfExists('billing_cycles');
    }
};
