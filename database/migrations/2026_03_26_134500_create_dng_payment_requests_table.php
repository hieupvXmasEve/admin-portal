<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dng_payment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->string('campus_code', 20);
            $table->string('student_code', 50)->comment('DNG StudentId e.g. SWB001');
            $table->string('fee_type', 20);
            $table->string('item_id', 100);
            $table->decimal('amount', 15, 2);
            $table->string('status', 30)->default('pending');
            $table->string('dng_transaction_id', 100)->nullable();
            $table->string('dng_payment_id', 100)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments');
            $table->string('psp_code', 50)->nullable();
            $table->string('invoice_serial_number', 255)->nullable();
            $table->timestamp('invoice_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('push_payload')->nullable();
            $table->json('push_response')->nullable();
            $table->json('qr_payload')->nullable();
            $table->json('last_callback_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique('dng_payment_id');
            $table->index('student_id');
            $table->index('status');
            $table->index('item_id');
            $table->index(['campus_code', 'item_id', 'student_code'], 'dng_pr_reconciliation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dng_payment_requests');
    }
};
