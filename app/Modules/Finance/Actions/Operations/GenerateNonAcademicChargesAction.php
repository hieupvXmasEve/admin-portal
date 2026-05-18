<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Models\FinanceCharge;
use App\Models\Student;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Enums\NonAcademicChargeTypeEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generate non-academic charges for a list of students from a CSV upload.
 *
 * Decisions honoured:
 *  D3 — NO voucher/scholarship resolver called.
 *  D7 — Duplicate = skip + report (same student_id + charge_type + semester_id + status=active).
 *  D8 — Campus resolved via app('campus')->id; students not on current campus are skipped.
 *  D9 — No batch history table; source_type='NonAcademicChargeBatch', source_id=null.
 *
 * Transaction: DB::beginTransaction() + per-student inner try/catch + DB::commit(),
 * mirroring GenerateBatchChargesAction lines 85-367.
 */
class GenerateNonAcademicChargesAction
{
    /**
     * @param  array{
     *     fee_type: string,
     *     semester_id: int,
     *     amount: string|float,
     *     due_date: string,
     *     note: string,
     *     student_codes: array<string>,
     * } $data
     * @return array{
     *     created: array<int, array{student_code: string, charge_id: int}>,
     *     skipped: array<int, array{student_code: string, reason: string}>,
     *     summary: array{total: int, created: int, skipped: int},
     * }
     */
    public static function run(array $data): array
    {
        $feeType = $data['fee_type'];
        $semesterId = (int) $data['semester_id'];
        $amount = (float) $data['amount'];
        $dueDate = $data['due_date'];
        $note = $data['note'] ?? '';
        $studentCodes = $data['student_codes'];
        $createdByUserId = auth()->id();

        // Resolve current campus — canonical pattern from GenerateBatchChargesAction:53-58.
        // A missing or null campus context is an unrecoverable misconfiguration; throw rather
        // than silently operating without a campus scope (which would match ALL campuses).
        if (! app()->bound('campus') || app('campus')->id === null) {
            throw new \RuntimeException('No campus context resolved; cannot generate charges.');
        }
        $campusId = app('campus')->id;

        // Load students in the CSV list scoped to current campus.
        $query = Student::whereIn('student_id', $studentCodes)
            ->where('campus_id', $campusId);
        // Keyed by student_id code for O(1) lookup.
        $foundStudents = $query->get()->keyBy('student_id');

        // Load ALL students matching the codes regardless of campus,
        // so we can distinguish "not found at all" from "wrong campus".
        $existsAnywhere = Student::whereIn('student_id', $studentCodes)
            ->pluck('student_id')
            ->flip()
            ->all();

        $created = [];
        $skipped = [];

        $createAction = app(CreateFinanceChargeAction::class);

        DB::beginTransaction();
        try {
            foreach ($studentCodes as $code) {
                try {
                    // Skip: code contains formula-injection chars or fails format check.
                    // Blocks CSV formula injection (=, +, -, @) and enforces DB column constraints.
                    if (! preg_match('/^[A-Z0-9]{4,20}$/', $code)) {
                        $skipped[] = ['student_code' => $code, 'reason' => 'invalid_code_format'];

                        continue;
                    }

                    // Skip: student not found in system at all.
                    if (! isset($existsAnywhere[$code])) {
                        $skipped[] = ['student_code' => $code, 'reason' => 'student_not_found'];

                        continue;
                    }

                    // Skip: student exists but belongs to a different campus.
                    if (! isset($foundStudents[$code])) {
                        $skipped[] = ['student_code' => $code, 'reason' => 'wrong_campus'];

                        continue;
                    }

                    $student = $foundStudents[$code];

                    // Skip: duplicate active charge for same (student, type, semester).
                    $existingCharge = FinanceCharge::where('student_id', $student->id)
                        ->where('charge_type', $feeType)
                        ->where('semester_id', $semesterId)
                        ->where('status', FinanceCharge::STATUS_ACTIVE)
                        ->first();

                    if ($existingCharge) {
                        $skipped[] = [
                            'student_code' => $code,
                            'reason' => 'duplicate_existing_charge_id_'.$existingCharge->id,
                        ];

                        continue;
                    }

                    // Create charge + invoice line via shared action.
                    // D3: no voucher/scholarship resolver called here.
                    $charge = $createAction->handle([
                        'student_id' => $student->id,
                        'semester_id' => $semesterId,
                        'charge_type' => $feeType,
                        'amount' => $amount,
                        'description' => $note !== '' ? $note : NonAcademicChargeTypeEnum::from($feeType)->label(),
                        'effective_at' => now(),
                        'due_date' => $dueDate,
                        'source_type' => 'NonAcademicChargeBatch',
                        'source_id' => null,
                        'created_by_user_id' => $createdByUserId,
                    ]);

                    $created[] = ['student_code' => $code, 'charge_id' => $charge->id];

                } catch (\Throwable $e) {
                    // Per-student error does not abort the whole batch.
                    $skipped[] = ['student_code' => $code, 'reason' => 'error: '.$e->getMessage()];
                    Log::warning('GenerateNonAcademicChargesAction: per-student error', [
                        'student_code' => $code,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('GenerateNonAcademicChargesAction: batch transaction failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'summary' => [
                'total' => count($studentCodes),
                'created' => count($created),
                'skipped' => count($skipped),
            ],
        ];
    }
}
