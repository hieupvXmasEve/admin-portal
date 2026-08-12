<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Major;

use App\Models\VoucherApplication;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Support\PendingScholarshipRestorationReader;
use App\Modules\Finance\Support\StudentChargeTimingResolver;
use App\Modules\Finance\Support\VoucherDiscountAmountResolver;
use App\Shared\Contracts\Academic\PendingScholarshipAdjustmentReader;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMajorChargesAction
{
    /**
     * @param  array{semester_id: int, due_date: string, student_ids: array<int, int>}  $data
     * @return array{created: int, skipped: int, failed: int, errors: array<int, string>, deferred_scholarship_review: array<int, int>, deferred_scholarship_restoration_pending: array<int, int>}
     */
    public static function run(array $data): array
    {
        $semesterId = (int) $data['semester_id'];
        $dueDate = Carbon::parse($data['due_date']);
        $studentIds = collect($data['student_ids'] ?? [])
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $timingResolver = app(StudentChargeTimingResolver::class);
        $submitTuition = app(SubmitTuitionTermDebitAction::class);
        $invoiceService = app(InvoiceGenerationService::class);
        $voucherDiscountAmountResolver = app(VoucherDiscountAmountResolver::class);

        $stats = [
            'created' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
            'deferred_scholarship_review' => [],
            'deferred_scholarship_restoration_pending' => [],
        ];

        if ($studentIds->isEmpty()) {
            return $stats;
        }

        $students = app(StudentReferenceReader::class)->findMany($studentIds->all());
        $enrollments = app(ProgramEnrollmentReader::class)->forStudentIds(array_keys($students));

        // Set-based, once per run: students with an in-flight scholarship-
        // adjustment dossier for this semester must not get a new tuition_term
        // charge (timing invariant — see plan skip-tuition-generation-pending-scholarship-review).
        $inFlightByStudent = app(PendingScholarshipAdjustmentReader::class)
            ->inFlightByStudent($studentIds->all(), $semesterId);

        // Same timing invariant, mirrored for a carried-forward adjustment with
        // a pending_approval restoration proposal (Phase 5, scholarship-restoration-watchlist).
        // THIS is the live batch-studio commit path (BatchStudioController::
        // commitCharges -> GenerateMajorChargesAction) — the sibling
        // GenerateBatchChargesAction is a billing-exception repair tool only.
        $pendingRestorationByStudent = app(PendingScholarshipRestorationReader::class)
            ->pendingByStudent($studentIds->all(), $semesterId);

        DB::beginTransaction();
        try {
            foreach ($students as $studentId => $student) {
                try {
                    $enrollment = $enrollments[$studentId]
                        ?? app(ProgramEnrollmentReader::class)->forStudentId((int) $studentId);
                    if (! in_array($enrollment->studyStage, ['intake_course', 'intake_major'], true)) {
                        $stats['skipped']++;

                        continue;
                    }

                    if (self::hasActiveHpCharge((int) $studentId, $semesterId)) {
                        $stats['skipped']++;

                        continue;
                    }

                    if (! $timingResolver->shouldGenerateTuitionForSemester($enrollment, $semesterId)) {
                        $stats['skipped']++;

                        continue;
                    }

                    if (! $submitTuition->isChargeable((int) $studentId, $semesterId)) {
                        $stats['skipped']++;

                        continue;
                    }

                    // Checked only after confirming tuition was actually due this
                    // semester — otherwise a not-due student would be reported as
                    // "deferred" and wrongly notified that tuition is on hold.
                    if ($inFlightByStudent[$studentId] ?? false) {
                        $stats['skipped']++;
                        $stats['deferred_scholarship_review'][] = (int) $studentId;

                        continue;
                    }

                    if ($pendingRestorationByStudent[$studentId] ?? false) {
                        $stats['skipped']++;
                        $stats['deferred_scholarship_restoration_pending'][] = (int) $studentId;

                        continue;
                    }

                    $result = $submitTuition->handle((int) $studentId, $semesterId, [
                        'source_kind' => SubmitTuitionTermDebitAction::SOURCE_KIND_BATCH_STUDIO,
                        'due_date' => $dueDate->toDateString(),
                    ]);

                    $charge = FinanceCharge::query()->find($result->finance_charge_id);
                    $line = InvoiceLine::query()->with('invoice')->find($result->invoice_line_id);
                    $obligation = FinanceObligation::query()->find($result->finance_obligation_id);

                    if (
                        ! $charge instanceof FinanceCharge
                        || ! $line instanceof InvoiceLine
                        || ! $obligation instanceof FinanceObligation
                    ) {
                        throw new \RuntimeException(
                            "Tuition intake materialization incomplete for student {$student->id}."
                        );
                    }

                    // Scholarship is applied by CreateFinanceChargeAction for tuition_term.
                    // Vouchers remain generator-side until wave 4 discount entitlements.
                    $invoice = $line->invoice ?? StudentInvoice::query()->find($line->invoice_id);
                    if ($invoice instanceof StudentInvoice) {
                        self::applyVoucherDiscounts(
                            $invoiceService,
                            $voucherDiscountAmountResolver,
                            $invoice,
                            (int) $studentId,
                            $semesterId,
                        );
                    }

                    $stats['created']++;
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $stats['errors'][] = "Student {$student->studentCode}: ".$e->getMessage();
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Major Tuition Charge Generation Failed: '.$e->getMessage());
            throw $e;
        }

        return $stats;
    }

    private static function hasActiveHpCharge(int $studentId, int $semesterId): bool
    {
        return FinanceCharge::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * Auto-apply any redeemed-but-unused voucher applications (invoice_id IS NULL) to this invoice.
     */
    private static function applyVoucherDiscounts(
        InvoiceGenerationService $invoiceService,
        VoucherDiscountAmountResolver $voucherDiscountAmountResolver,
        StudentInvoice $invoice,
        int $studentId,
        int $semesterId,
    ): void {
        $voucherApplications = VoucherApplication::query()
            ->with('voucherDefinition')
            ->where('student_id', $studentId)
            ->get();

        foreach ($voucherApplications as $voucherApp) {
            if ($voucherApp->invoice_id) {
                continue;
            }

            $discountAmount = (float) $voucherApp->discount_amount;

            if ($discountAmount <= 0 && $voucherApp->voucherDefinition) {
                $resolved = $voucherDiscountAmountResolver->resolveAmounts(
                    $voucherApp->voucherDefinition,
                    $studentId,
                    $semesterId,
                );
                $discountAmount = (float) $resolved['discount_amount'];
            }

            if ($discountAmount > 0) {
                $invoiceService->applyInvoiceDiscount(
                    $invoice,
                    'voucher',
                    $discountAmount,
                    VoucherApplication::class,
                    'Voucher Applied ('.$voucherApp->voucherDefinition?->code.')',
                    (int) $voucherApp->id,
                );

                $voucherApp->update([
                    'invoice_id' => $invoice->id,
                    'discount_amount' => $discountAmount,
                ]);

                continue;
            }

            $voucherApp->update([
                'invoice_id' => $invoice->id,
            ]);
        }
    }
}
