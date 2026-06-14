<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB-08 / FIN-14: voucher_applications.invoice_id and finance_charge_id were
 * soft references (indexes only, no FK) so a deleted invoice/charge could leave
 * orphaned voucher rows pointing at missing ids. Add SET NULL foreign keys so
 * the reference clears instead of dangling (DB-21 soft-reference convention).
 *
 * Pre-checked clean: 0 orphan invoice_id / finance_charge_id rows at migration
 * authoring time, so the FK adds without violating existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_applications', function (Blueprint $table) {
            $table->foreign('invoice_id', 'voucher_applications_invoice_id_foreign')
                ->references('id')->on('student_invoices')
                ->nullOnDelete();

            $table->foreign('finance_charge_id', 'voucher_applications_finance_charge_id_foreign')
                ->references('id')->on('finance_charges')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('voucher_applications', function (Blueprint $table) {
            $table->dropForeign('voucher_applications_invoice_id_foreign');
            $table->dropForeign('voucher_applications_finance_charge_id_foreign');
        });
    }
};
