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
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->index('voucher_id');
            $table->index('student_id');
            $table->index('invoice_id');
        });

        // Add foreign key constraint only if student_invoices table exists
        if (Schema::hasTable('student_invoices')) {
            Schema::table('voucher_redemptions', function (Blueprint $table) {
                $table->foreign('invoice_id')->references('id')->on('student_invoices')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_redemptions');
    }
};
