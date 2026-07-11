<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hardens Finance Cancellation Operation durability:
 * - Academic handoff outbox (pending source → Finance request)
 * - Append-only completion outbox (drop one-row-per-operation unique)
 * - Provider cancel attempt ledger columns on the operation
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_finance_cancellation_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system');
            $table->string('source_kind');
            $table->string('source_ref');
            $table->string('obligation_type');
            $table->string('unpaid_void_reason');
            $table->string('paid_void_reason');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['source_system', 'source_kind', 'source_ref', 'obligation_type'],
                'academic_fin_cancel_handoff_source_unique',
            );
            $table->index(['status', 'id'], 'academic_fin_cancel_handoff_status_idx');
        });

        Schema::table('finance_cancellation_completion_outbox', function (Blueprint $table): void {
            // Unique index also backs the FK on MariaDB — drop FK first, then unique.
            $table->dropForeign('fin_cancel_outbox_operation_fk');
            $table->dropUnique('finance_cancellation_completion_outbox_operation_unique');
        });

        Schema::table('finance_cancellation_completion_outbox', function (Blueprint $table): void {
            $table->string('event_kind')->default('completed')->after('event_id');
            $table->unsignedInteger('event_version')->default(1)->after('event_kind');
            $table->foreign('finance_cancellation_operation_id', 'fin_cancel_outbox_operation_fk')
                ->references('id')
                ->on('finance_cancellation_operations')
                ->cascadeOnDelete();
            $table->index(
                ['finance_cancellation_operation_id', 'event_version'],
                'fin_cancel_outbox_operation_version_idx',
            );
        });

        Schema::table('finance_cancellation_operations', function (Blueprint $table): void {
            $table->json('provider_attempts')->nullable()->after('result_payload');
            $table->timestamp('processing_claimed_at')->nullable()->after('provider_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('finance_cancellation_operations', function (Blueprint $table): void {
            $table->dropColumn(['provider_attempts', 'processing_claimed_at']);
        });

        Schema::table('finance_cancellation_completion_outbox', function (Blueprint $table): void {
            $table->dropForeign('fin_cancel_outbox_operation_fk');
            $table->dropIndex('fin_cancel_outbox_operation_version_idx');
            $table->dropColumn(['event_kind', 'event_version']);
            $table->unique('finance_cancellation_operation_id', 'finance_cancellation_completion_outbox_operation_unique');
            $table->foreign('finance_cancellation_operation_id', 'fin_cancel_outbox_operation_fk')
                ->references('id')
                ->on('finance_cancellation_operations')
                ->cascadeOnDelete();
        });

        Schema::dropIfExists('academic_finance_cancellation_handoffs');
    }
};
