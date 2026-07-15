<?php

declare(strict_types=1);

use App\Models\VoucherApplication;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('voucher_applications', 'finance_charge_id')) {
            return;
        }

        $invalidPointer = DB::table('voucher_applications as application')
            ->leftJoin('finance_charges as charge', 'charge.id', '=', 'application.finance_charge_id')
            ->whereNotNull('application.finance_charge_id')
            ->where(function ($query): void {
                $query->whereNull('charge.id')
                    ->orWhereNull('charge.source_type')
                    ->orWhere('charge.source_type', '!=', VoucherApplication::class)
                    ->orWhereNull('charge.source_id')
                    ->orWhereColumn('charge.source_id', '!=', 'application.id');
            })
            ->exists();
        if ($invalidPointer) {
            throw new RuntimeException(
                'Cannot retire voucher_applications.finance_charge_id: every legacy pointer must agree with Finance-owned VoucherApplication source evidence.'
            );
        }

        Schema::table('voucher_applications', function (Blueprint $table): void {
            $table->dropForeign('voucher_applications_finance_charge_id_foreign');
            $table->dropColumn('finance_charge_id');
        });
    }

    /** Forward-only retirement: voucher source rows must not regain a ledger pointer. */
    public function down(): void {}
};
