<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_charge_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_charge_id')
                ->constrained('finance_charges')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_no');
            $table->decimal('amount', 15, 2);
            $table->date('due_date');
            $table->string('status', 20)->default('pending');
            $table->foreignId('dng_payment_request_id')
                ->nullable()
                ->constrained('dng_payment_requests')
                ->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedSmallInteger('push_attempt_count')->default(0);
            $table->text('last_push_error')->nullable();
            $table->timestamp('last_push_attempted_at')->nullable();
            $table->timestamps();

            $table->unique(['finance_charge_id', 'installment_no'], 'fci_charge_no_unique');
            $table->index(['status', 'due_date'], 'fci_status_due_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_charge_installments');
    }
};
