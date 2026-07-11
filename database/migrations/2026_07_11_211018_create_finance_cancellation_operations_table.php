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
        Schema::create('finance_cancellation_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system');
            $table->string('source_kind');
            $table->string('source_ref');
            $table->string('obligation_type');
            $table->string('status');
            $table->string('unpaid_void_reason');
            $table->string('paid_void_reason');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('source_payload');
            $table->json('result_payload')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'source_kind', 'source_ref', 'obligation_type'], 'finance_cancellation_operations_source_unique');
        });

        Schema::create('finance_cancellation_completion_outbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('finance_cancellation_operation_id')
                ->constrained('finance_cancellation_operations', 'id', 'fin_cancel_outbox_operation_fk')
                ->cascadeOnDelete();
            $table->string('event_id')->unique();
            $table->string('status')->default('pending');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->unique('finance_cancellation_operation_id', 'finance_cancellation_completion_outbox_operation_unique');
        });

        Schema::table('course_retake_registrations', function (Blueprint $table): void {
            $table->enum('status', [
                'approved', 'payment_pending', 'finance_pending_cancellation', 'paid', 'enrolled', 'cancelled',
            ])->default('approved')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_retake_registrations', function (Blueprint $table): void {
            $table->enum('status', ['approved', 'payment_pending', 'paid', 'enrolled', 'cancelled'])->default('approved')->change();
        });
        Schema::dropIfExists('finance_cancellation_completion_outbox');
        Schema::dropIfExists('finance_cancellation_operations');
    }
};
