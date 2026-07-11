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
        Schema::create('finance_dng_receipt_exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('evidence_hash', 64)->unique();
            $table->string('exception_type', 32);
            $table->string('status', 16)->default('open');
            $table->foreignId('dng_payment_request_id')->nullable()->constrained('dng_payment_requests')->restrictOnDelete();
            $table->foreignId('dng_webhook_event_id')->nullable()->constrained('dng_webhook_events')->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->restrictOnDelete();
            $table->string('provider_payment_id', 100)->nullable();
            $table->json('mismatch_reasons');
            $table->json('affected_scope');
            $table->json('raw_provider_evidence');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'exception_type'], 'finance_dng_receipt_exception_queue_idx');
            $table->index('dng_payment_request_id', 'finance_dng_receipt_exception_request_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_dng_receipt_exceptions');
    }
};
