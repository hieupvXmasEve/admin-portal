<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-10b (Story 4): a DNG pivot row is an allocation line of THIS request, not
 * "how much the student still owes". When a DNG is built from an installment
 * plan, each pivot must carry the installment it collects so allocation truth
 * matches installment truth. This adds the nullable link; legacy / ad-hoc rows
 * (no installment) keep it null.
 *
 * The FK constraint is named explicitly — the auto-generated name
 * (dng_payment_request_charges_finance_charge_installment_id_foreign) exceeds
 * MySQL's 64-char identifier limit.
 */
return new class extends Migration
{
    private const FK_NAME = 'dng_req_charge_installment_fk';

    public function up(): void
    {
        if (Schema::hasColumn('dng_payment_request_charges', 'finance_charge_installment_id')) {
            return;
        }

        Schema::table('dng_payment_request_charges', function (Blueprint $table) {
            $table->unsignedBigInteger('finance_charge_installment_id')
                ->nullable()
                ->after('finance_charge_id');

            $table->foreign('finance_charge_installment_id', self::FK_NAME)
                ->references('id')
                ->on('finance_charge_installments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dng_payment_request_charges', function (Blueprint $table) {
            $table->dropForeign(self::FK_NAME);
            $table->dropColumn('finance_charge_installment_id');
        });
    }
};
