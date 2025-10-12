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

        // Update voucher_redemptions table to add billing_cycle_id and status
        if (Schema::hasTable('voucher_redemptions')) {
            Schema::table('voucher_redemptions', function (Blueprint $table) {
                // Make redeemed_at nullable
                $table->timestamp('redeemed_at')->nullable()->change();

                $table->foreignId('billing_cycle_id')
                    ->nullable()
                    ->after('student_id')
                    ->constrained('billing_cycles')
                    ->nullOnDelete();

                $table->enum('status', ['pending', 'redeemed', 'expired', 'cancelled'])
                    ->default('pending')
                    ->after('invoice_id');

                $table->index('billing_cycle_id');
                $table->index('status');
            });
        }
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
