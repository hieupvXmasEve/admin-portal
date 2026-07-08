<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\FinanceCharge;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Models\FinanceObligation;
use Illuminate\Support\Facades\DB;

class BackfillLegacyRetakeResitObligationsAction
{
    /**
     * @return array{
     *     checked:int,
     *     created:int,
     *     already_linked:int,
     *     linked_existing:int,
     *     skipped:int,
     *     mismatches:int,
     *     details:array<int, array<string, mixed>>
     * }
     */
    public function run(bool $dryRun = false): array
    {
        return DB::transaction(function () use ($dryRun): array {
            $summary = [
                'checked' => 0,
                'created' => 0,
                'already_linked' => 0,
                'linked_existing' => 0,
                'skipped' => 0,
                'mismatches' => 0,
                'details' => [],
            ];

            FinanceCharge::query()
                ->whereIn('charge_type', [
                    FinanceCharge::TYPE_RETAKE_FEE,
                    FinanceCharge::TYPE_EXAM_RESIT_FEE,
                ])
                ->whereIn('status', [
                    FinanceCharge::STATUS_ACTIVE,
                    FinanceCharge::STATUS_VOID,
                ])
                ->where(function ($query): void {
                    $query
                        ->whereNull('finance_obligation_id')
                        ->orWhereNotNull('source_type')
                        ->orWhereNotNull('source_id');
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->chunkById(200, function ($charges) use (&$summary, $dryRun): void {
                    foreach ($charges as $charge) {
                        $summary['checked']++;
                        $this->backfillCharge($charge, $summary, $dryRun);
                    }
                });

            return $summary;
        });
    }

    /**
     * @param  array{
     *     checked:int,
     *     created:int,
     *     already_linked:int,
     *     linked_existing:int,
     *     skipped:int,
     *     mismatches:int,
     *     details:array<int, array<string, mixed>>
     * }  $summary
     */
    private function backfillCharge(FinanceCharge $charge, array &$summary, bool $dryRun): void
    {
        $source = $this->resolveSource($charge);

        if ($source === null) {
            $summary['skipped']++;
            $summary['details'][] = $this->detail($charge, 'skipped', 'unsupported_or_missing_source');

            return;
        }

        if ($this->amountsDiffer((float) $charge->amount, $source['legacy_source_amount'])) {
            $summary['mismatches']++;
            $summary['details'][] = $this->detail($charge, 'mismatch', 'amount_mismatch', $source);
        }

        $obligation = $this->findObligation($source);

        if ($obligation instanceof FinanceObligation) {
            $this->linkExistingObligation($charge, $obligation, $summary, $dryRun);

            return;
        }

        if ($dryRun) {
            $summary['created']++;

            return;
        }

        $obligation = FinanceObligation::query()->create([
            'source_system' => AcademicFinanceObligationSource::SOURCE_SYSTEM,
            'source_kind' => $source['source_kind'],
            'source_ref' => $source['source_ref'],
            'obligation_type' => $charge->charge_type,
            'lifecycle_status' => $charge->status === FinanceCharge::STATUS_VOID
                ? FinanceObligation::STATUS_VOIDED
                : FinanceObligation::STATUS_ACCEPTED,
            'amount' => $charge->amount,
            'currency' => 'VND',
            'pricing_rule_version' => "{$charge->charge_type}:legacy_backfill",
            'pricing_snapshot' => $this->pricingSnapshot($charge, $source),
            'accepted_at' => $charge->status === FinanceCharge::STATUS_ACTIVE
                ? ($charge->created_at ?? now())
                : null,
        ]);

        $charge->update(['finance_obligation_id' => $obligation->id]);
        $summary['created']++;
    }

    /**
     * @param  array{
     *     checked:int,
     *     created:int,
     *     already_linked:int,
     *     linked_existing:int,
     *     skipped:int,
     *     mismatches:int,
     *     details:array<int, array<string, mixed>>
     * }  $summary
     */
    private function linkExistingObligation(
        FinanceCharge $charge,
        FinanceObligation $obligation,
        array &$summary,
        bool $dryRun,
    ): void {
        if ((int) $charge->finance_obligation_id === (int) $obligation->id) {
            $summary['already_linked']++;

            return;
        }

        $linkedCharge = $obligation->financeCharge()->first();

        if (
            $charge->finance_obligation_id !== null
            || ($linkedCharge instanceof FinanceCharge && (int) $linkedCharge->id !== (int) $charge->id)
        ) {
            $summary['skipped']++;
            $summary['details'][] = $this->detail($charge, 'skipped', 'obligation_link_conflict');

            return;
        }

        if (! $dryRun) {
            $charge->update(['finance_obligation_id' => $obligation->id]);
        }

        $summary['linked_existing']++;
    }

    /**
     * @return array{
     *     source_kind:string,
     *     source_ref:string,
     *     legacy_source_amount:float,
     *     source_amount_column:string
     * }|null
     */
    private function resolveSource(FinanceCharge $charge): ?array
    {
        if ($charge->source_type === null || $charge->source_id === null) {
            return null;
        }

        $sourceType = ltrim($charge->source_type, '\\');
        $sourceId = (int) $charge->source_id;

        if (
            $charge->charge_type === FinanceCharge::TYPE_RETAKE_FEE
            && $sourceType === CourseRetakeRegistration::class
        ) {
            $registration = CourseRetakeRegistration::query()
                ->select(['id', 'retake_fee'])
                ->find($sourceId);

            if (! $registration instanceof CourseRetakeRegistration) {
                return null;
            }

            return [
                'source_kind' => AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
                'source_ref' => AcademicFinanceObligationSource::courseRetakeRegistrationRef($registration),
                'legacy_source_amount' => (float) $registration->retake_fee,
                'source_amount_column' => 'course_retake_registrations.retake_fee',
            ];
        }

        if (
            $charge->charge_type === FinanceCharge::TYPE_EXAM_RESIT_FEE
            && $sourceType === ExamResitAttempt::class
        ) {
            $attempt = ExamResitAttempt::query()
                ->select(['id', 'fee_amount'])
                ->find($sourceId);

            if (! $attempt instanceof ExamResitAttempt) {
                return null;
            }

            return [
                'source_kind' => AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT,
                'source_ref' => AcademicFinanceObligationSource::examResitAttemptRef($attempt),
                'legacy_source_amount' => (float) $attempt->fee_amount,
                'source_amount_column' => 'exam_resit_attempts.fee_amount',
            ];
        }

        return null;
    }

    /**
     * @param  array{source_kind:string, source_ref:string, legacy_source_amount:float, source_amount_column:string}  $source
     */
    private function findObligation(array $source): ?FinanceObligation
    {
        return FinanceObligation::query()
            ->where('source_system', AcademicFinanceObligationSource::SOURCE_SYSTEM)
            ->where('source_kind', $source['source_kind'])
            ->where('source_ref', $source['source_ref'])
            ->where('obligation_type', $this->obligationTypeFor($source['source_kind']))
            ->lockForUpdate()
            ->first();
    }

    private function obligationTypeFor(string $sourceKind): string
    {
        return match ($sourceKind) {
            AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION => FinanceCharge::TYPE_RETAKE_FEE,
            AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        };
    }

    /**
     * @param  array{source_kind:string, source_ref:string, legacy_source_amount:float, source_amount_column:string}  $source
     * @return array<string, mixed>
     */
    private function pricingSnapshot(FinanceCharge $charge, array $source): array
    {
        return [
            'provenance' => 'legacy_backfill',
            'legacy_charge_id' => (int) $charge->id,
            'legacy_charge_status' => $charge->status,
            'legacy_charge_amount' => (float) $charge->amount,
            'legacy_source_type' => $charge->source_type,
            'legacy_source_id' => (int) $charge->source_id,
            'legacy_source_amount' => $source['legacy_source_amount'],
            'source_amount_column' => $source['source_amount_column'],
        ];
    }

    /**
     * @param  array{source_kind?:string, source_ref?:string, legacy_source_amount?:float, source_amount_column?:string}  $source
     * @return array<string, mixed>
     */
    private function detail(FinanceCharge $charge, string $status, string $reason, array $source = []): array
    {
        return [
            'status' => $status,
            'reason' => $reason,
            'charge_id' => (int) $charge->id,
            'charge_type' => $charge->charge_type,
            'charge_status' => $charge->status,
            'charge_amount' => (float) $charge->amount,
            'source_type' => $charge->source_type,
            'source_id' => $charge->source_id === null ? null : (int) $charge->source_id,
            'source_kind' => $source['source_kind'] ?? null,
            'source_ref' => $source['source_ref'] ?? null,
            'legacy_source_amount' => $source['legacy_source_amount'] ?? null,
            'source_amount_column' => $source['source_amount_column'] ?? null,
        ];
    }

    private function amountsDiffer(float $chargeAmount, float $sourceAmount): bool
    {
        return abs(round($chargeAmount, 2) - round($sourceAmount, 2)) > 0.009;
    }
}
