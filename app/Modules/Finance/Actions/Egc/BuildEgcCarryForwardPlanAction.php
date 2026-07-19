<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Egc;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Support\EgcBlockFinanceResolver;
use App\Shared\Contracts\Academic\AcademicFinanceChargeSourceGateway;
use App\Shared\Contracts\Academic\DTO\AcademicEgcBlockData;
use App\Shared\Contracts\Academic\ProgramEnrollmentReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;

class BuildEgcCarryForwardPlanAction
{
    public function run(int|object $student, int $semesterId): array
    {
        $studentId = is_int($student) ? $student : (int) ($student->id ?? 0);
        $reference = app(StudentReferenceReader::class)->find($studentId);
        if ($studentId <= 0 || $reference === null) {
            throw new \InvalidArgumentException('Student reference was not found for EGC carry-forward.');
        }

        $enrollment = app(ProgramEnrollmentReader::class)->forStudentId($studentId);
        $activeCharges = FinanceCharge::query()
            ->where('student_id', $studentId)
            ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->with([
                'semester:id,name',
                'invoiceLines.invoice:id,invoice_number,student_id,semester_id',
                'invoiceLines.paymentApplications',
                'invoiceLines.discountAllocations',
            ])
            ->get();
        $payments = Payment::query()
            ->where('student_id', $studentId)
            ->where('status', Payment::STATUS_COMPLETED)
            ->with('applications')
            ->get();

        $academicSources = app(AcademicFinanceChargeSourceGateway::class);
        $egcBlocks = collect($academicSources->egcBlocksForStudent($studentId));
        $chargesByBlock = app(EgcBlockFinanceResolver::class)->chargesFor($egcBlocks);
        $consumedBlocks = $egcBlocks
            ->filter(function ($block) use ($chargesByBlock) {
                if (! $chargesByBlock->has($block->id)) {
                    return false;
                }

                if ($block->result !== AcademicEgcBlockData::RESULT_PENDING) {
                    return true;
                }

                return app(AcademicFinanceChargeSourceGateway::class)->egcBlockHasMatchedRegistration((int) $block->id);
            })
            ->sortBy(['semester_id', 'block_number'])
            ->values();

        $consumedChargeIds = $consumedBlocks
            ->map(fn (AcademicEgcBlockData $block): ?int => $chargesByBlock->get($block->id)?->id)
            ->filter()
            ->values()
            ->all();

        $activeCharges = $activeCharges
            ->filter(fn (FinanceCharge $charge): bool => $charge->amount > 0)
            ->sortBy(['semester_id', 'id'])
            ->values();

        $semesterCharges = $activeCharges
            ->filter(fn ($charge) => (int) $charge->semester_id === $semesterId)
            ->values();

        $unusedCharges = $semesterCharges
            ->reject(fn ($charge) => in_array((int) $charge->id, $consumedChargeIds, true))
            ->values();

        $issues = [];
        $unusedChargeRows = [];

        foreach ($unusedCharges as $charge) {
            $activeLines = $charge->invoiceLines
                ->filter(fn ($line) => ($line->status ?? 'active') === 'active')
                ->values();

            if ($activeLines->count() !== 1) {
                $issues[] = "Charge #{$charge->id} must have exactly one active invoice line.";

                continue;
            }

            $line = $activeLines->first();
            $netDiscount = (float) $line->discountAllocations->sum('amount');

            if ($netDiscount > 0) {
                $issues[] = "Charge #{$charge->id} has discount allocations and needs manual review.";

                continue;
            }

            $paidAmount = max(0, (float) $line->paymentApplications->sum('amount'));

            $unusedChargeRows[] = [
                'id' => $charge->id,
                'description' => $charge->description,
                'semester_id' => $charge->semester_id,
                'semester_name' => $charge->semester?->name,
                'amount' => (float) $charge->amount,
                'paid_amount' => $paidAmount,
                'releaseable_amount' => $paidAmount,
                'invoice_id' => $line->invoice_id,
                'invoice_number' => $line->invoice?->invoice_number,
                'invoice_line_id' => $line->id,
            ];
        }

        $eligibleReleaseAmount = collect($unusedChargeRows)->sum('releaseable_amount');
        $currentUnappliedBalance = $payments->sum(function (Payment $payment) {
            return max(0, (float) $payment->amount - (float) $payment->applications->sum('amount'));
        });

        $status = 'ineligible';
        $reason = 'No active EGC charges found for the selected semester.';

        if ($enrollment->studyStage === 'intake_pre_uni_gc') {
            $reason = 'Student is still an active EGC student.';
        } elseif (! empty($issues)) {
            $status = 'needs_data_repair';
            $reason = 'Data repair is required before releasing unused EGC balance.';
        } elseif ($unusedCharges->isEmpty()) {
            $reason = 'All selected-semester EGC charges are already consumed by mapped EGC blocks.';
        } elseif ($eligibleReleaseAmount <= 0) {
            $reason = 'Unused EGC charges exist, but no paid amount is available to release.';
        } else {
            $status = 'eligible';
            $reason = 'Unused paid EGC charges can be released to unapplied balance.';
        }

        return [
            'student_id' => $studentId,
            'student_name' => $reference->fullName,
            'student_code' => $reference->studentCode,
            'student_status' => $enrollment->legacyCompatibleStatus(),
            'semester_id' => $semesterId,
            'charged_levels_count' => $activeCharges->count(),
            'consumed_levels_count' => $consumedBlocks->count(),
            'current_unapplied_balance' => (float) $currentUnappliedBalance,
            'eligible_release_amount' => (float) $eligibleReleaseAmount,
            'status' => $status,
            'reason' => $reason,
            'issues' => array_values(array_unique($issues)),
            'unused_charges' => $unusedChargeRows,
            'consumed_blocks' => $consumedBlocks->map(fn ($block) => [
                'id' => $block->id,
                'semester_id' => $block->semester_id,
                'semester_name' => $block->semester_name,
                'block_number' => $block->block_number,
                'level_number' => $block->level_number,
                'finance_source_ref' => app(EgcBlockFinanceResolver::class)->sourceRef($block),
            ])->values()->all(),
        ];
    }
}
