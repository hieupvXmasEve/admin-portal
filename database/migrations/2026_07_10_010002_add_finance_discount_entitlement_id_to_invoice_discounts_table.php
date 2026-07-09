<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_discounts', function (Blueprint $table) {
            $table->foreignId('finance_discount_entitlement_id')
                ->nullable()
                ->after('reference_id')
                ->constrained('finance_discount_entitlements')
                ->nullOnDelete();

            $table->index(
                'finance_discount_entitlement_id',
                'invoice_discounts_finance_discount_entitlement_id_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('invoice_discounts', function (Blueprint $table) {
            $table->dropForeign(['finance_discount_entitlement_id']);
            $table->dropIndex('invoice_discounts_finance_discount_entitlement_id_idx');
            $table->dropColumn('finance_discount_entitlement_id');
        });
    }
};
