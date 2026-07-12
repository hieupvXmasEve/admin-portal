<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestReservationTarget;
use App\Modules\Finance\Dng\Support\DngActiveMigrationReport;
use App\Modules\Finance\Dng\Support\DngReservationTargetFingerprint;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Queries\Dng\DngActiveMigrationInventoryQuery;
use Illuminate\Support\Facades\DB;

final class BackfillActiveDngMigrationAction
{
    private const ACTIVE_STATUSES = [
        DngPaymentRequest::STATUS_PENDING,
        DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
        DngPaymentRequest::STATUS_NEEDS_REVIEW,
        DngPaymentRequest::STATUS_PAID_UNINVOICED,
        DngPaymentRequest::STATUS_PAID_INVOICED,
        DngPaymentRequest::STATUS_RECONCILED,
    ];

    public function __construct(private readonly DngActiveMigrationInventoryQuery $inventory) {}

    /** @param array{limit?: int|null} $data */
    public static function run(array $data = []): DngActiveMigrationReport
    {
        return app(self::class)->handle($data['limit'] ?? null);
    }

    public function handle(?int $limit = null): DngActiveMigrationReport
    {
        $report = $this->inventory->handle($limit);
        $backfilled = 0;

        foreach ($report->records as $record) {
            if (! in_array('backfill_pending', $record['classifications'], true)
                || array_diff($record['classifications'], ['exact_link', 'backfill_pending']) !== []) {
                continue;
            }

            $inspection = $this->inventory->inspectionFor($record['id']);
            $request = DngPaymentRequest::query()->findOrFail($record['id']);
            $applied = DB::transaction(function () use ($request, $inspection): bool {
                $locked = DngPaymentRequest::query()->lockForUpdate()->findOrFail($request->id);
                $billingAccount = BillingAccount::query()->lockForUpdate()->findOrFail($inspection['billing_account_id']);
                $slotKey = $this->slotKey((int) $billingAccount->id, (string) $inspection['campus_code'], (string) $inspection['provider_rail'], (string) $locked->fee_type);

                $duplicate = DngPaymentRequest::query()
                    ->where('id', '!=', $locked->id)
                    ->where('active_slot_key', $slotKey)
                    ->whereIn('status', self::ACTIVE_STATUSES)
                    ->exists();
                if ($duplicate) {
                    return false;
                }

                $targets = $inspection['targets'];
                $locked->update([
                    'billing_account_id' => $billingAccount->id,
                    'provider_rail' => $inspection['provider_rail'],
                    'active_slot_key' => $slotKey,
                    'captured_settlement_version' => (int) $billingAccount->settlement_version,
                    'target_fingerprint' => DngReservationTargetFingerprint::make($targets),
                    'reserved_at' => $locked->reserved_at ?? now(),
                ]);

                foreach ($targets as $target) {
                    DngPaymentRequestReservationTarget::query()->firstOrCreate(
                        [
                            'dng_payment_request_id' => $locked->id,
                            'target_identity' => $target['target_identity'],
                        ],
                        [
                            'invoice_line_id' => $target['invoice_line_id'],
                            'finance_charge_installment_id' => $target['finance_charge_installment_id'],
                            'captured_collectible' => $target['collectible'],
                        ],
                    );
                }

                return true;
            });

            if ($applied) {
                $backfilled++;
            }
        }

        $final = $this->inventory->handle($limit);

        return new DngActiveMigrationReport($final->total, $final->counts, $final->records, $backfilled, $final->complete);
    }

    private function slotKey(int $billingAccountId, string $campusCode, string $providerRail, string $feeType): string
    {
        return implode(':', [$providerRail, $campusCode, $billingAccountId, $feeType]);
    }
}
