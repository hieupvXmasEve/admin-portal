<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NT4 / DB-14: the invoice snapshot columns are a rebuildable cache derived from
 * the ledger, not a source of truth. Renaming them to cached_* makes that
 * explicit so future code does not mistake them for canonical balance values.
 * Only SettlementService::recalculateInvoiceSnapshot writes them; the model
 * exposes the canonical balance through ledger-derived accessors.
 */
return new class extends Migration
{
    /**
     * @var array<string, string> old name => new (cached) name
     */
    private array $renames = [
        'subtotal' => 'cached_subtotal',
        'discount_total' => 'cached_discount_total',
        'total_amount' => 'cached_total_amount',
        'paid_amount' => 'cached_paid_amount',
        'paid_at' => 'cached_paid_at',
    ];

    public function up(): void
    {
        Schema::table('student_invoices', function (Blueprint $table) {
            foreach ($this->renames as $from => $to) {
                if (Schema::hasColumn('student_invoices', $from) && ! Schema::hasColumn('student_invoices', $to)) {
                    $table->renameColumn($from, $to);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_invoices', function (Blueprint $table) {
            foreach ($this->renames as $from => $to) {
                if (Schema::hasColumn('student_invoices', $to) && ! Schema::hasColumn('student_invoices', $from)) {
                    $table->renameColumn($to, $from);
                }
            }
        });
    }
};
