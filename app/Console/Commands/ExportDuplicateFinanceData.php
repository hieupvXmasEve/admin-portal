<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Read-only legacy inventory for Finance data review:
 *
 *   - multiple student_invoices for the same (student_id, semester_id), which
 *     are allowed by the current architecture and may represent distinct fees;
 *   - INV-11: duplicate dng_webhook_events.payload_hash
 *             -> blocks restore of unique(payload_hash) (DB-04)
 *
 * It NEVER mutates data. The console table is a compact triage view; the JSON
 * report (always written) carries the full row-level detail — invoice_line,
 * charge, payment_application, discount and DNG ids with amounts/statuses. A
 * multi-invoice group is informational, not proof of duplicate billing.
 *
 * --limit only caps the DETAILED rows collected; the reported totals always
 * reflect the true number of duplicate groups in the dataset.
 * See docs/stories/E-finance-module-review-2026-06/S-003-data-guards-and-constraints/.
 */
class ExportDuplicateFinanceData extends Command
{
    protected $signature = 'finance:export-duplicate-finance-data
        {--json= : Write the full structured report to this JSON path (default: storage/app/finance/s003-duplicate-export.json)}
        {--limit=0 : Cap the number of DETAILED groups collected per section (0 = all). Totals are unaffected.}';

    protected $description = 'Read-only export of allowed multi-invoice groups and duplicate webhook payload hashes (INV-11)';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $this->line('');
        $this->info('📤 Duplicate finance data export (READ-ONLY — no rows are changed).');
        $this->line('');

        $invoiceTotal = $this->countMultiInvoiceGroups();
        $payloadTotal = $this->countDuplicatePayloadHashGroups();

        $invoiceGroups = $this->collectMultiInvoiceGroups($limit);
        $this->renderInvoiceGroups($invoiceGroups, $invoiceTotal);

        $this->line('');

        $payloadGroups = $this->collectDuplicatePayloadHashGroups($limit);
        $this->renderPayloadHashGroups($payloadGroups, $payloadTotal);

        $report = [
            'report_version' => 2,
            'generated_for' => 'finance-multi-invoice-and-webhook-review',
            'read_only' => true,
            'limit_applied' => $limit,
            'totals' => [
                'multi_invoice_groups_total' => $invoiceTotal,
                'multi_invoice_groups_shown' => count($invoiceGroups),
                // Deprecated v1 aliases retained for external/manual consumers.
                'inv6_invoice_groups_total' => $invoiceTotal,
                'inv6_invoice_groups_shown' => count($invoiceGroups),
                'inv11_payload_hash_groups_total' => $payloadTotal,
                'inv11_payload_hash_groups_shown' => count($payloadGroups),
            ],
            'multi_invoice_groups' => $invoiceGroups,
            'duplicate_invoice_groups' => $invoiceGroups,
            'duplicate_payload_hash_groups' => $payloadGroups,
            'deprecated_fields' => [
                'totals.inv6_invoice_groups_total' => 'Use totals.multi_invoice_groups_total; same-semester invoices are allowed.',
                'totals.inv6_invoice_groups_shown' => 'Use totals.multi_invoice_groups_shown; same-semester invoices are allowed.',
                'duplicate_invoice_groups' => 'Use multi_invoice_groups; this alias will be removed in a future report version.',
            ],
        ];

        $path = $this->option('json') ?: storage_path('app/finance/s003-duplicate-export.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("📝 Full row-level report (ids/amounts/statuses): {$path}");

        $this->line('');
        $this->warn(sprintf(
            'Allowed multi-invoice groups: %d (showing %d) · INV-11 groups: %d (showing %d). Multi-invoice rows are informational; review only for business-specific cleanup.',
            $invoiceTotal,
            count($invoiceGroups),
            $payloadTotal,
            count($payloadGroups),
        ));
        $this->line('');

        return self::SUCCESS;
    }

    private function countMultiInvoiceGroups(): int
    {
        return DB::table('student_invoices')
            ->select('student_id', 'semester_id')
            ->groupBy('student_id', 'semester_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
    }

    private function countDuplicatePayloadHashGroups(): int
    {
        return DB::table('dng_webhook_events')
            ->select('payload_hash')
            ->groupBy('payload_hash')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectMultiInvoiceGroups(int $limit): array
    {
        $keys = DB::table('student_invoices')
            ->select('student_id', 'semester_id', DB::raw('COUNT(*) as invoice_count'))
            ->groupBy('student_id', 'semester_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('student_id')
            ->orderBy('semester_id');

        if ($limit > 0) {
            $keys->limit($limit);
        }

        $groups = [];
        foreach ($keys->get() as $key) {
            $invoices = DB::table('student_invoices as si')
                ->where('si.student_id', $key->student_id)
                ->where('si.semester_id', $key->semester_id)
                ->orderBy('si.id')
                ->get()
                ->map(fn ($si) => [
                    'invoice_id' => $si->id,
                    'invoice_number' => $si->invoice_number,
                    'status' => $si->status,
                    'cached_total_amount' => (float) $si->cached_total_amount,
                    'cached_paid_amount' => (float) $si->cached_paid_amount,
                    'created_at' => $si->created_at,
                    'lines' => $this->invoiceLines($si->id),
                    'payment_applications' => $this->invoicePaymentApplications($si->id),
                    'discounts' => $this->invoiceDiscounts($si->id),
                    'dng_requests' => $this->invoiceDngRequests($si->id),
                ])->all();

            $groups[] = [
                'student_id' => $key->student_id,
                'semester_id' => $key->semester_id,
                'invoice_count' => (int) $key->invoice_count,
                'invoices' => $invoices,
            ];
        }

        return $groups;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function invoiceLines(int $invoiceId): array
    {
        return DB::table('invoice_lines as il')
            ->leftJoin('finance_charges as fc', 'fc.id', '=', 'il.charge_id')
            ->where('il.invoice_id', $invoiceId)
            ->orderBy('il.id')
            ->get(['il.id', 'il.charge_id', 'fc.charge_type', 'il.amount_snapshot', 'il.status'])
            ->map(fn ($l) => [
                'line_id' => $l->id,
                'charge_id' => $l->charge_id,
                'charge_type' => $l->charge_type,
                'amount_snapshot' => (float) $l->amount_snapshot,
                'status' => $l->status,
            ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function invoicePaymentApplications(int $invoiceId): array
    {
        return DB::table('payment_applications as pa')
            ->join('invoice_lines as il', 'il.id', '=', 'pa.invoice_line_id')
            ->where('il.invoice_id', $invoiceId)
            ->orderBy('pa.id')
            ->get(['pa.id', 'pa.payment_id', 'pa.invoice_line_id', 'pa.amount', 'pa.entry_type'])
            ->map(fn ($pa) => [
                'payment_application_id' => $pa->id,
                'payment_id' => $pa->payment_id,
                'invoice_line_id' => $pa->invoice_line_id,
                'amount' => (float) $pa->amount,
                'entry_type' => $pa->entry_type,
            ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function invoiceDiscounts(int $invoiceId): array
    {
        return DB::table('invoice_discounts')
            ->where('invoice_id', $invoiceId)
            ->orderBy('id')
            ->get(['id', 'discount_type', 'amount', 'status', 'reference_id'])
            ->map(fn ($d) => [
                'discount_id' => $d->id,
                'discount_type' => $d->discount_type,
                'amount' => (float) $d->amount,
                'status' => $d->status,
                'reference_id' => $d->reference_id,
            ])->all();
    }

    /**
     * DNG requests linked to this invoice's charges through BOTH link styles:
     *  - direct: dng_payment_requests.finance_charge_id (single-charge requests)
     *  - pivot:  dng_payment_request_charges.finance_charge_id (aggregate/multi-
     *            charge requests, whose dpr.finance_charge_id may be NULL)
     *
     * Missing the pivot path would falsely report DNG: 0 for aggregate requests
     * and mislead a merge/consolidate decision.
     *
     * @return array<int, array<string, mixed>>
     */
    private function invoiceDngRequests(int $invoiceId): array
    {
        $byId = [];

        $direct = DB::table('dng_payment_requests as dpr')
            ->join('invoice_lines as il', 'il.charge_id', '=', 'dpr.finance_charge_id')
            ->where('il.invoice_id', $invoiceId)
            ->distinct()
            ->get(['dpr.id', 'dpr.status', 'dpr.amount', 'dpr.fee_type']);

        foreach ($direct as $r) {
            $byId[$r->id] = [
                'dng_request_id' => $r->id,
                'status' => $r->status,
                'amount' => (float) $r->amount,
                'fee_type' => $r->fee_type,
                'link_source' => 'direct',
                'pivot_amount' => null,
            ];
        }

        $pivot = DB::table('dng_payment_request_charges as pc')
            ->join('invoice_lines as il', 'il.charge_id', '=', 'pc.finance_charge_id')
            ->join('dng_payment_requests as dpr', 'dpr.id', '=', 'pc.dng_payment_request_id')
            ->where('il.invoice_id', $invoiceId)
            ->get(['dpr.id', 'dpr.status', 'dpr.amount', 'dpr.fee_type', 'pc.amount as pivot_amount']);

        foreach ($pivot as $r) {
            if (isset($byId[$r->id])) {
                $byId[$r->id]['link_source'] = $byId[$r->id]['link_source'] === 'pivot' ? 'pivot' : 'direct+pivot';
                $byId[$r->id]['pivot_amount'] = (float) ($byId[$r->id]['pivot_amount'] ?? 0) + (float) $r->pivot_amount;

                continue;
            }

            $byId[$r->id] = [
                'dng_request_id' => $r->id,
                'status' => $r->status,
                'amount' => (float) $r->amount,
                'fee_type' => $r->fee_type,
                'link_source' => 'pivot',
                'pivot_amount' => (float) $r->pivot_amount,
            ];
        }

        ksort($byId);

        return array_values($byId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectDuplicatePayloadHashGroups(int $limit): array
    {
        $keys = DB::table('dng_webhook_events')
            ->select('payload_hash', DB::raw('COUNT(*) as event_count'))
            ->groupBy('payload_hash')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc(DB::raw('COUNT(*)'));

        if ($limit > 0) {
            $keys->limit($limit);
        }

        $groups = [];
        foreach ($keys->get() as $key) {
            $events = DB::table('dng_webhook_events')
                ->where('payload_hash', $key->payload_hash)
                ->orderBy('id')
                ->get()
                ->map(fn ($e) => [
                    'event_id' => $e->id,
                    'event_type' => $e->event_type,
                    'processing_status' => $e->processing_status,
                    'is_valid_checksum' => (bool) $e->is_valid_checksum,
                    'dng_payment_request_id' => $e->dng_payment_request_id,
                    'received_at' => $e->received_at,
                ])->all();

            $groups[] = [
                'payload_hash' => $key->payload_hash,
                'event_count' => (int) $key->event_count,
                'events' => $events,
            ];
        }

        return $groups;
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     */
    private function renderInvoiceGroups(array $groups, int $total): void
    {
        $this->line("── INFO: allowed multi-invoice groups (student_id, semester_id) — {$total} group(s)");
        if ($groups === []) {
            $this->info('   ✅ none');

            return;
        }

        $rows = [];
        foreach ($groups as $g) {
            foreach ($g['invoices'] as $i => $inv) {
                $rows[] = [
                    $i === 0 ? "S{$g['student_id']}/SEM{$g['semester_id']} (x{$g['invoice_count']})" : '',
                    $inv['invoice_id'],
                    $inv['invoice_number'],
                    $inv['status'],
                    number_format($inv['cached_total_amount']),
                    number_format($inv['cached_paid_amount']),
                    count($inv['lines']),
                    count($inv['payment_applications']),
                    count($inv['discounts']),
                    count($inv['dng_requests']),
                ];
            }
        }

        $this->table(
            ['Group', 'Inv id', 'Number', 'Status', 'Total', 'Paid', 'Lines', 'PayApps', 'Disc', 'DNG'],
            $rows,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     */
    private function renderPayloadHashGroups(array $groups, int $total): void
    {
        $this->line("── INV-11: duplicate webhook payload_hash — {$total} group(s) — blocks DB-04 unique restore");
        if ($groups === []) {
            $this->info('   ✅ none');

            return;
        }

        $rows = [];
        foreach ($groups as $g) {
            foreach ($g['events'] as $i => $ev) {
                $rows[] = [
                    $i === 0 ? substr($g['payload_hash'], 0, 12).'… (x'.$g['event_count'].')' : '',
                    $ev['event_id'],
                    $ev['event_type'],
                    $ev['processing_status'],
                    $ev['is_valid_checksum'] ? 'yes' : 'no',
                    $ev['dng_payment_request_id'] ?? '—',
                    $ev['received_at'] ?? '—',
                ];
            }
        }

        $this->table(
            ['Payload hash', 'Event id', 'Type', 'Proc status', 'Checksum', 'DNG req', 'Received'],
            $rows,
        );
    }
}
