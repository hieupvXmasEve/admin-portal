<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Egc;

use App\Models\EgcBlock;
use App\Models\FinanceCharge;
use App\Models\Payment;
use App\Models\Student;

class BuildEgcCarryForwardPlanAction
{
    public function run(Student|int $student, int $semesterId): array
    {
        $student = $student instanceof Student
            ? $student
            : Student::query()
                ->with([
                    'egcProgress.semester:id,name',
                    'financeCharges' => fn ($query) => $query
                        ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
                        ->where('status', FinanceCharge::STATUS_ACTIVE)
                        ->with([
                            'semester:id,name',
                            'invoiceLines.invoice:id,invoice_number,student_id,semester_id',
                            'invoiceLines.paymentApplications',
                            'invoiceLines.discountAllocations',
                        ]),
                    'payments' => fn ($query) => $query
                        ->where('status', Payment::STATUS_COMPLETED)
                        ->with('applications'),
                ])
                ->findOrFail($student);

        $consumedBlocks = $student->egcProgress
            ->filter(fn ($block) => $block->finance_charge_id !== null && $block->result !== EgcBlock::RESULT_PENDING)
            ->sortBy(['semester_id', 'block_number'])
            ->values();

        $consumedChargeIds = $consumedBlocks
            ->pluck('finance_charge_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $activeCharges = $student->financeCharges
            ->filter(fn ($charge) => $charge->amount > 0)
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
        $currentUnappliedBalance = $student->payments->sum(function ($payment) {
            return max(0, (float) $payment->amount - (float) $payment->applications->sum('amount'));
        });

        $status = 'ineligible';
        $reason = 'No active EGC charges found for the selected semester.';

        if ($student->status === 'intake_pre_uni_gc') {
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
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'student_code' => $student->student_id,
            'student_status' => $student->status,
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
                'semester_name' => $block->semester?->name,
                'block_number' => $block->block_number,
                'level_number' => $block->level_number,
                'finance_charge_id' => $block->finance_charge_id,
            ])->values()->all(),
        ];
    }
}
