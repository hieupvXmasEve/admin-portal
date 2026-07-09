<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_discount_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_account_id')
                ->nullable()
                ->constrained('billing_accounts')
                ->restrictOnDelete();
            $table->string('source_system', 50);
            $table->string('source_kind', 100);
            $table->string('source_ref', 120);
            $table->string('entitlement_type', 50);
            $table->enum('lifecycle_status', [
                'requested',
                'approved',
                'rejected',
                'cancelled',
                'revoked',
                'expired',
            ])->default('requested');
            $table->enum('allocation_status', [
                'available',
                'partially_allocated',
                'fully_allocated',
                'released',
            ])->default('available');
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->default('VND');
            $table->string('pricing_rule_version', 100);
            $table->json('pricing_snapshot');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['source_system', 'source_kind', 'source_ref', 'entitlement_type'],
                'finance_discount_entitlements_source_quad_unique'
            );
            $table->index(
                ['entitlement_type', 'lifecycle_status'],
                'finance_discount_entitlements_type_status_idx'
            );
            $table->index('billing_account_id', 'finance_discount_entitlements_billing_account_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_discount_entitlements');
    }
};
