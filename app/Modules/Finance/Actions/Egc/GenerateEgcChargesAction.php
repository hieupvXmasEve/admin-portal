<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Egc;

use App\Models\EgcBlock;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Student;
use App\Models\StudentInvoice;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateEgcChargesAction
{
    public const CHARGE_AMOUNT = 15_000_000;

    /**
     * Generate EGC charges for a list of students in a semester.
     *
     * @param  array{semester_id: int, due_date: string, students: array<array{student_id: int, block_count: int}>}  $data
     */
    public static function run(array $data): array
    {
        $semesterId = (int) $data['semester_id'];
        $dueDate = $data['due_date'];
        $createdByUserId = auth()->id();
        $results = ['created' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($data['students'] as $studentData) {
            $studentId = (int) $studentData['student_id'];
            $blockCount = (int) ($studentData['block_count'] ?? 2);
            $student = Student::query()
                ->select(['id', 'gc_current_level', 'gc_total_levels'])
                ->find($studentId);
            $currentLevel = $student?->gc_current_level !== null
                ? (int) $student->gc_current_level
                : (int) ($studentData['current_level'] ?? 0);
            $totalLevels = $student?->gc_total_levels !== null
                ? (int) $student->gc_total_levels
                : 0;

            // Student đang học current level → phí dự kiến cho level tiếp theo
            // Student đã học xong → phí cho current level hiện tại
            $isStudying = self::isStudyingLevel($studentId, $currentLevel);
            $effectiveStartLevel = $isStudying ? $currentLevel + 1 : $currentLevel;

            if ($totalLevels <= 0 || $effectiveStartLevel >= $totalLevels) {
                $results['skipped'] += $blockCount;

                continue;
            }

            $existingChargeCount = FinanceCharge::where('student_id', $studentId)
                ->where('semester_id', $semesterId)
                ->where('charge_type', FinanceCharge::TYPE_EGC_LEVEL_FEE)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->count();

            if ($existingChargeCount >= 2) {
                $results['skipped'] += $blockCount;

                continue;
            }

            try {
                DB::transaction(function () use ($studentId, $semesterId, $dueDate, $blockCount, $effectiveStartLevel, $totalLevels, $existingChargeCount, $createdByUserId, &$results) {
                    self::generateForStudent($studentId, $semesterId, $dueDate, $blockCount, $effectiveStartLevel, $totalLevels, $existingChargeCount, $createdByUserId, $results);
                });
            } catch (\Exception $e) {
                Log::error('GenerateEgcChargesAction failed', [
                    'student_id' => $studentId,
                    'semester_id' => $semesterId,
                    'error' => $e->getMessage(),
                ]);
                $results['errors'][] = ['student_id' => $studentId, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    private static function generateForStudent(
        int $studentId,
        int $semesterId,
        string $dueDate,
        int $blockCount,
        int $currentLevel,
        int $totalLevels,
        int $existingChargeCount,
        ?int $createdByUserId,
        array &$results
    ): void {
        $deferredBlocks = EgcBlock::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->whereNull('finance_charge_id')
            ->get();

        foreach ($deferredBlocks as $block) {
            $charge = self::createCharge($studentId, $semesterId, $dueDate, $block->level_number, $createdByUserId);
            $block->update(['finance_charge_id' => $charge->id]);
            $results['created']++;
        }

        $existingBlockNumbers = EgcBlock::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->pluck('block_number')
            ->toArray();

        $nextBlockNumber = empty($existingBlockNumbers) ? 1 : (max($existingBlockNumbers) + 1);
        $blocksToCreate = max(0, $blockCount - $existingChargeCount);

        if ($blocksToCreate === 0) {
            $results['skipped'] += $blockCount;
        }

        for ($i = 0; $i < $blocksToCreate; $i++) {
            $blockNumber = $nextBlockNumber + $i;
            $levelNumber = $currentLevel + $i;

            if ($levelNumber >= $totalLevels) {
                $results['skipped']++;

                continue;
            }

            if (in_array($blockNumber, $existingBlockNumbers, true)) {
                $results['skipped']++;

                continue;
            }

            $isRetake = self::isRetakeEligible($studentId, $levelNumber);

            try {
                $charge = self::createCharge($studentId, $semesterId, $dueDate, $levelNumber, $createdByUserId);

                EgcBlock::create([
                    'student_id' => $studentId,
                    'semester_id' => $semesterId,
                    'block_number' => $blockNumber,
                    'level_number' => $levelNumber,
                    'result' => EgcBlock::RESULT_PENDING,
                    'is_retake' => $isRetake,
                    'finance_charge_id' => $charge->id,
                ]);

                $results['created']++;
            } catch (UniqueConstraintViolationException) {
                $results['skipped']++;
            }
        }

        if ($blockCount === 1) {
            $deferredBlockNumber = $nextBlockNumber + 1;
            $deferredLevel = $currentLevel + 1;

            if ($deferredLevel < $totalLevels && ! in_array($deferredBlockNumber, $existingBlockNumbers, true)) {
                try {
                    EgcBlock::create([
                        'student_id' => $studentId,
                        'semester_id' => $semesterId + 1, // next semester placeholder — actual semester set at charge gen time
                        'block_number' => $deferredBlockNumber,
                        'level_number' => $deferredLevel,
                        'result' => EgcBlock::RESULT_PENDING,
                        'is_retake' => false,
                        'finance_charge_id' => null,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // already deferred, skip
                }
            }
        }
    }

    private static function isStudyingLevel(int $studentId, int $levelNumber): bool
    {
        return DB::table('academic_records as ar')
            ->join('units', 'ar.unit_id', '=', 'units.id')
            ->where('ar.student_id', $studentId)
            ->where('units.unit_type', 'egc')
            ->where('units.level', $levelNumber)
            ->where('ar.completion_status', 'in_progress')
            ->where('ar.semester_id', function ($query) use ($studentId): void {
                $query->selectRaw('MAX(ar2.semester_id)')
                    ->from('academic_records as ar2')
                    ->join('units as u2', 'ar2.unit_id', '=', 'u2.id')
                    ->where('ar2.student_id', $studentId)
                    ->where('u2.unit_type', 'egc');
            })
            ->exists();
    }

    private static function isRetakeEligible(int $studentId, int $levelNumber): bool
    {
        return EgcBlock::where('student_id', $studentId)
            ->where('level_number', $levelNumber)
            ->where('result', EgcBlock::RESULT_FAIL)
            ->where('attendance_rate', '>=', 80)
            ->whereNull('retake_discount_id') // entitlement not yet consumed
            ->exists();
    }

    private static function createCharge(
        int $studentId,
        int $semesterId,
        string $dueDate,
        int $levelNumber,
        ?int $createdByUserId
    ): FinanceCharge {
        $charge = FinanceCharge::create([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
            'amount' => self::CHARGE_AMOUNT,
            'description' => "EGC Level {$levelNumber} Fee",
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
            'created_by_user_id' => $createdByUserId,
        ]);

        // Assign charge to invoice (find draft or create new), always sync due_date
        $invoice = StudentInvoice::firstOrCreate(
            ['student_id' => $studentId, 'semester_id' => $semesterId, 'billing_cycle_id' => null],
            [
                'invoice_number' => 'EGC-'.$studentId.'-'.$semesterId.'-'.now()->format('mdHis').rand(100, 999),
                'status' => 'draft',
                'due_date' => $dueDate,
            ]
        );

        // Keep due_date in sync even if invoice already existed
        if (! $invoice->wasRecentlyCreated) {
            $invoice->update(['due_date' => $dueDate]);
        }

        InvoiceLine::updateOrCreate(
            ['invoice_id' => $invoice->id, 'charge_id' => $charge->id],
            ['amount_snapshot' => $charge->amount, 'description_snapshot' => $charge->description]
        );

        $invoice->recalculateTotals();

        return $charge;
    }
}
