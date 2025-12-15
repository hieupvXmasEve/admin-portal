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
        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('voucher_definitions')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            // Foreign key for billing_cycle_id will be added in a later migration after billing_cycles table exists
            $table->unsignedBigInteger('billing_cycle_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->enum('status', ['pending', 'redeemed', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->index('voucher_id');
            $table->index('student_id');
            $table->index('billing_cycle_id');
            $table->index('invoice_id');
            $table->index('status');
        });

        // Foreign key for invoice_id will be added in a later migration
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_redemptions');
    }
};
