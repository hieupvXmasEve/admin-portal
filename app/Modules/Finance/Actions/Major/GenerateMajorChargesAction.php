<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Major;

use App\Models\Student;
use App\Models\VoucherApplication;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Support\StudentChargeTimingResolver;
use App\Modules\Finance\Support\VoucherDiscountAmountResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMajorChargesAction
{
    /**
     * @param  array{semester_id: int, due_date: string, student_ids: array<int, int>}  $data
     * @return array{created: int, skipped: int, failed: int, errors: array<int, string>}
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
        ];

        if ($studentIds->isEmpty()) {
            return $stats;
        }

        $students = Student::query()
            ->with(['scholarshipAward.scholarshipDefinition', 'voucherApplications.voucherDefinition'])
            ->whereIn('id', $studentIds)
            ->whereIn('status', ['intake_course', 'intake_major'])
            ->get();

        DB::beginTransaction();
        try {
            foreach ($students as $student) {
                try {
                    if (self::hasActiveHpCharge($student->id, $semesterId)) {
                        $stats['skipped']++;

                        continue;
                    }

                    if (! $timingResolver->shouldGenerateTuitionForSemester($student, $semesterId)) {
                        $stats['skipped']++;

                        continue;
                    }

                    if (! $submitTuition->isChargeable($student, $semesterId)) {
                        $stats['skipped']++;

                        continue;
                    }

                    $result = $submitTuition->handle($student, $semesterId, [
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
                            $student,
                            $semesterId,
                        );
                    }

                    $stats['created']++;
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $stats['errors'][] = "Student {$student->student_id}: ".$e->getMessage();
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
        Student $student,
        int $semesterId,
    ): void {
        foreach ($student->voucherApplications as $voucherApp) {
            if ($voucherApp->invoice_id) {
                continue;
            }

            $discountAmount = (float) $voucherApp->discount_amount;

            if ($discountAmount <= 0 && $voucherApp->voucherDefinition) {
                $resolved = $voucherDiscountAmountResolver->resolveAmounts(
                    $voucherApp->voucherDefinition,
                    $student,
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
                    'finance_charge_id' => null,
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
