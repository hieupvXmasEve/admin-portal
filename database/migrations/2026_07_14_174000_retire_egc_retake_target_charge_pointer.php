<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('egc_retake_discount_links', 'target_finance_charge_id')) {
            return;
        }

        Schema::table('egc_retake_discount_links', function (Blueprint $table): void {
            $table->foreignId('target_invoice_line_id')
                ->nullable()
                ->after('source_egc_block_id')
                ->constrained('invoice_lines')
                ->restrictOnDelete();
        });

        $links = DB::table('egc_retake_discount_links')->orderBy('id')->get(['id', 'invoice_discount_id']);
        foreach ($links as $link) {
            $lineIds = DB::table('discount_allocations')
                ->where('invoice_discount_id', $link->invoice_discount_id)
                ->where('amount', '>', 0)
                ->pluck('invoice_line_id')
                ->unique()
                ->values();
            if ($lineIds->count() !== 1) {
                throw new RuntimeException(
                    "Cannot retire EGC retake target charge pointer for link {$link->id}: expected one exact positive discount allocation line."
                );
            }

            DB::table('egc_retake_discount_links')
                ->where('id', $link->id)
                ->update(['target_invoice_line_id' => $lineIds->first(), 'updated_at' => now()]);
        }

        Schema::table('egc_retake_discount_links', function (Blueprint $table): void {
            $table->dropForeign(['target_finance_charge_id']);
            $table->dropUnique(['target_finance_charge_id']);
            $table->dropColumn('target_finance_charge_id');
            $table->unique('target_invoice_line_id');
        });
    }

    /** Forward-only retirement: the Finance-owned invoice-line audit link remains. */
    public function down(): void {}
};
