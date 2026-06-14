<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * FIN-17 / DB-04: restore the unique(payload_hash) idempotency guard on
 * dng_webhook_events that was dropped in 2026_03_27_111002.
 *
 * Without it, every duplicate inbound webhook (DNG retries the SAME call) creates
 * a fresh event row + queue job → retry storms and state-machine races. A
 * payload_hash is a deterministic SHA-256 of the full payload, so two rows can
 * only share a hash when the payload is byte-identical — i.e. an exact retry of
 * the same call. Call 1 and Call 2 of a payment carry different payloads (the
 * InvoiceSerialNumber/InvoiceDate differ), so they hash differently and are both
 * preserved; the unique guard only collapses retries, never the two-call pair.
 *
 * Per the epic rule "do not add a constraint until the matching dirty-data check
 * is zero", this migration first collapses any pre-existing exact-duplicate rows
 * (keeping the most authoritative one per hash by processing_status priority, ties
 * broken by earliest id) so the constraint can apply cleanly on databases that
 * accumulated duplicates while the unique guard was absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->collapseExactDuplicateRows();

        Schema::table('dng_webhook_events', function (Blueprint $table) {
            // The 2026_03_27 migration replaced the unique with a plain index;
            // drop it so the column carries a single (unique) index.
            $table->dropIndex(['payload_hash']);
            $table->unique('payload_hash');
        });
    }

    public function down(): void
    {
        Schema::table('dng_webhook_events', function (Blueprint $table) {
            $table->dropUnique('dng_webhook_events_payload_hash_unique');
            $table->index('payload_hash');
        });
    }

    /**
     * Status priority for choosing which duplicate row to KEEP — higher wins. We
     * keep the most authoritative/resolved row, not just the earliest, so a
     * processed retry is never discarded in favour of an earlier failed/orphan
     * attempt (which would both lose the real audit trail and let a future
     * identical retry be mis-handled). Ties break to the lowest id (earliest).
     */
    private const STATUS_PRIORITY = [
        'processed' => 6,
        'skipped' => 5,
        'mismatch' => 4,
        'failed_terminal' => 3,
        'failed_retryable' => 2,
        'processing' => 1,
        'received' => 0,
        'pending' => 0,
    ];

    /**
     * Collapse exact-duplicate dng_webhook_events rows (same payload_hash) so the
     * unique constraint can apply, keeping the most authoritative row per group by
     * processing_status priority. SELECT-then-DELETE in separate statements avoids
     * MariaDB error 1093 (cannot target the same table in a delete subquery).
     */
    private function collapseExactDuplicateRows(): void
    {
        $duplicateHashes = DB::table('dng_webhook_events')
            ->select('payload_hash')
            ->groupBy('payload_hash')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('payload_hash');

        if ($duplicateHashes->isEmpty()) {
            return;
        }

        $idsToDelete = [];

        foreach ($duplicateHashes->chunk(500) as $hashChunk) {
            $rows = DB::table('dng_webhook_events')
                ->whereIn('payload_hash', $hashChunk->all())
                ->get(['id', 'payload_hash', 'processing_status']);

            foreach ($rows->groupBy('payload_hash') as $group) {
                // Keep the highest-priority status; break ties by earliest id.
                $keep = $group->sortBy([
                    [fn ($row) => self::STATUS_PRIORITY[$row->processing_status] ?? -1, 'desc'],
                    [fn ($row) => $row->id, 'asc'],
                ])->first();

                foreach ($group as $row) {
                    if ($row->id !== $keep->id) {
                        $idsToDelete[] = $row->id;
                    }
                }
            }
        }

        if ($idsToDelete === []) {
            return;
        }

        $removed = 0;
        foreach (array_chunk($idsToDelete, 1000) as $idChunk) {
            $removed += DB::table('dng_webhook_events')->whereIn('id', $idChunk)->delete();
        }

        Log::warning('Collapsed duplicate dng_webhook_events rows before restoring unique(payload_hash)', [
            'removed' => $removed,
        ]);
    }
};
