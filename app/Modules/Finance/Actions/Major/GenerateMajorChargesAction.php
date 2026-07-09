<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Major;

use App\Models\ScholarshipDefinition;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\VoucherApplication;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Support\ScholarshipDiscountResolver;
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

        $createdByUserId = auth()->id();
        $timingResolver = app(StudentChargeTimingResolver::class);

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

        $invoiceService = app(InvoiceGenerationService::class);
        $voucherDiscountAmountResolver = app(VoucherDiscountAmountResolver::class);

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

                    $termData = $timingResolver->getTuitionTermData($student, $semesterId);
                    $amount = $termData['amount'];

                    if ($amount === null || $amount <= 0) {
                        $stats['skipped']++;

                        continue;
                    }

                    $termIdx = $termData['chargeable_term_index'] ?? $termData['term_number'] ?? 1;

                    $charge = FinanceCharge::create([
                        'student_id' => $student->id,
                        'semester_id' => $semesterId,
                        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
                        'description' => "Major Tuition (Installment {$termIdx})",
                        'amount' => $amount,
                        'source_type' => null,
                        'source_id' => null,
                        'status' => FinanceCharge::STATUS_ACTIVE,
                        'effective_at' => now(),
                        'created_by_user_id' => $createdByUserId,
                    ]);

                    $invoice = self::findReusableInvoice($student->id, $semesterId)
                        ?? self::createDraftInvoice($student, $semesterId, $dueDate);

                    InvoiceLine::firstOrCreate([
                        'invoice_id' => $invoice->id,
                        'charge_id' => $charge->id,
                    ], [
                        'amount_snapshot' => $charge->amount,
                        'description_snapshot' => $charge->description,
                    ]);

                    self::applyScholarshipDiscount(
                        $invoiceService,
                        $invoice,
                        $student->scholarshipAward,
                        (float) $charge->amount,
                    );

                    self::applyVoucherDiscounts(
                        $invoiceService,
                        $voucherDiscountAmountResolver,
                        $invoice,
                        $student,
                        $semesterId,
                    );

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

    private static function findReusableInvoice(int $studentId, int $semesterId): ?StudentInvoice
    {
        return StudentInvoice::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->reusableForChargeGeneration()
            ->latest('id')
            ->first();
    }

    private static function applyScholarshipDiscount(
        InvoiceGenerationService $invoiceService,
        StudentInvoice $invoice,
        ?StudentScholarshipAward $award,
        float $baseAmount,
    ): void {
        if (! $award || $baseAmount <= 0) {
            return;
        }

        $definition = $award->scholarshipDefinition;
        if (! $definition instanceof ScholarshipDefinition) {
            return;
        }

        // FIN-04/07: shared resolver is the single source of capped scholarship math.
        $discount = app(ScholarshipDiscountResolver::class)->resolve($definition, $baseAmount);

        if ($discount <= 0) {
            return;
        }

        $alreadyApplied = InvoiceDiscount::query()
            ->where('invoice_id', $invoice->id)
            ->where('discount_type', 'scholarship')
            ->where('reference_id', $award->id)
            ->where('discount_source', StudentScholarshipAward::class)
            ->exists();

        if ($alreadyApplied) {
            return;
        }

        $invoiceService->applyInvoiceDiscount(
            $invoice,
            'scholarship',
            $discount,
            StudentScholarshipAward::class,
            'Scholarship: '.$definition->name,
            (int) $award->id,
        );
    }

    /**
     * Auto-apply any redeemed-but-unused voucher applications (invoice_id IS NULL) to this invoice.
     * Mirrors the Operations batch flow: prefer the redeemed discount_amount, fall back to a fresh
     * resolution, and mark informational (zero-discount) vouchers as consumed without a discount line.
     */
    private static function applyVoucherDiscounts(
        InvoiceGenerationService $invoiceService,
        VoucherDiscountAmountResolver $voucherDiscountAmountResolver,
        StudentInvoice $invoice,
        Student $student,
        int $semesterId,
    ): void {
        foreach ($student->voucherApplications as $voucherApp) {
            // Rule: invoice_id IS NULL => not yet consumed.
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

            // Informational voucher — mark consumed on this invoice without a discount line.
            $voucherApp->update([
                'invoice_id' => $invoice->id,
            ]);
        }
    }

    private static function createDraftInvoice(Student $student, int $semesterId, Carbon $dueDate): StudentInvoice
    {
        return StudentInvoice::create([
            'student_id' => $student->id,
            'semester_id' => $semesterId,
            'invoice_number' => 'INV-'.time().'-'.$student->student_id.'-'.$semesterId,
            'due_date' => $dueDate,
            'opened_at' => now(),
            'status' => 'draft',
        ]);
    }
}
