<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Integrity;

use App\Models\InvoiceDiscount;
use App\Models\InvoiceLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Maps an invariant finding to an Audit Workspace target (§4.4). Most samples are
 * already a graphable target type; a few are resolved "up" (invoice_line → invoice,
 * invoice_discount → invoice, pivot → dng) and a couple have no graph target and
 * link to a list view instead (webhook event).
 */
class FinanceInvariantSampleResolver
{
    /** @var array<string,?string> */
    private const TARGET_TYPE = [
        'INV-1' => 'payment', 'INV-10' => 'payment',
        'INV-2' => 'invoice', 'INV-6' => 'invoice', 'INV-9' => 'invoice',
        'INV-3' => 'charge', 'INV-8' => 'charge', 'INV-13' => 'charge',
        'INV-4' => 'invoice',
        'INV-5' => 'invoice',
        'INV-7' => 'student',
        'INV-12' => 'dng', 'INV-14' => 'dng', 'INV-15' => 'dng',
        'INV-11' => null,
    ];

    public function targetTypeFor(string $code): ?string
    {
        return self::TARGET_TYPE[$code] ?? null;
    }

    public function listUrlFor(string $code): ?string
    {
        if ($code === 'INV-11' && Route::has('finance.dng.webhook-events.index')) {
            return route('finance.dng.webhook-events.index');
        }

        return null;
    }

    public function resolveTargetId(string $code, int $sampleId): ?int
    {
        return match ($code) {
            'INV-1', 'INV-10', 'INV-2', 'INV-6', 'INV-9', 'INV-3', 'INV-8', 'INV-13', 'INV-7', 'INV-12', 'INV-14' => $sampleId,
            'INV-4' => ($line = InvoiceLine::find($sampleId))?->invoice_id !== null ? (int) $line->invoice_id : null,
            'INV-5' => $this->invoiceOfDiscount($sampleId),
            'INV-15' => $this->dngOfPivot($sampleId),
            default => null,
        };
    }

    private function invoiceOfDiscount(int $discountId): ?int
    {
        $model = InvoiceDiscount::find($discountId);

        return $model?->invoice_id !== null ? (int) $model->invoice_id : null;
    }

    private function dngOfPivot(int $pivotId): ?int
    {
        $row = DB::table('dng_payment_request_charges')->where('id', $pivotId)->first();

        return $row?->dng_payment_request_id !== null ? (int) $row->dng_payment_request_id : null;
    }
}