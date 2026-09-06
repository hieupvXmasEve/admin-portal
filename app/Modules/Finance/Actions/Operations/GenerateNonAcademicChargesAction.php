<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Modules\Finance\Enums\NonAcademicChargeTypeEnum;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Support\FinanceOwnedObligationSource;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Generate non-academic charges for a list of students from a CSV upload.
 *
 * Wave 2 (BHYT tracer): each row enters through the Finance Intake Contract.
 * Finance mints the source_ref, prices via GeneratorAmount strategy, and the
 * materializer creates FinanceCharge + InvoiceLine. No direct charge create.
 *
 * Decisions honoured:
 *  D3 — NO voucher/scholarship resolver called.
 *  D7 — Duplicate = skip + report (same student_id + charge_type + semester_id + status=active).
 *  D8 — Campus resolved via app('campus')->id; students not on current campus are skipped.
 *  D9 — No batch history table; correlation is the obligation source triple.
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
     *     due_date?: string,
     *     note: string,
     *     student_codes: array<string>,
     * }  $data
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
        $dueDate = $data['due_date'] ?? null;
        $note = $data['note'] ?? '';
        $studentCodes = $data['student_codes'];

        // Resolve current campus — canonical pattern from GenerateBatchChargesAction:53-58.
        // A missing or null campus context is an unrecoverable misconfiguration; throw rather
        // than silently operating without a campus scope (which would match ALL campuses).
        if (! app()->bound('campus') || app('campus')->id === null) {
            throw new \RuntimeException('No campus context resolved; cannot generate charges.');
        }
        $campusId = app('campus')->id;

        /** @var StudentReferenceReader $studentReferences */
        $studentReferences = app(StudentReferenceReader::class);

        // Registry owns both code resolution and campus membership. Keep the
        // two lookups so the legacy skip reasons remain externally stable.
        $studentsByCode = collect($studentCodes)
            ->unique()
            ->mapWithKeys(static function (string $studentCode) use ($studentReferences, $campusId): array {
                $campusStudent = $studentReferences->findByStudentCode($studentCode, (int) $campusId);

                return [$studentCode => [
                    'student' => $campusStudent,
                    'exists' => $campusStudent !== null
                        || $studentReferences->findByStudentCodeAnywhere($studentCode) !== null,
                ]];
            });

        $created = [];
        $skipped = [];

        $intake = app(FinanceIntakeContract::class);

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
                    $studentLookup = $studentsByCode->get($code);
                    if (! $studentLookup['exists']) {
                        $skipped[] = ['student_code' => $code, 'reason' => 'student_not_found'];

                        continue;
                    }

                    // Skip: student exists but belongs to a different campus.
                    $student = $studentLookup['student'];
                    if ($student === null) {
                        $skipped[] = ['student_code' => $code, 'reason' => 'wrong_campus'];

                        continue;
                    }

                    // Skip: duplicate active charge for same (student, type, semester).
                    // lockForUpdate reduces double-submit races under the outer batch txn.
                    $existingCharge = FinanceCharge::where('student_id', $student->id)
                        ->where('charge_type', $feeType)
                        ->where('semester_id', $semesterId)
                        ->where('status', FinanceCharge::STATUS_ACTIVE)
                        ->lockForUpdate()
                        ->first();

                    if ($existingCharge) {
                        $skipped[] = [
                            'student_code' => $code,
                            'reason' => 'duplicate_existing_charge_id_'.$existingCharge->id,
                        ];

                        continue;
                    }

                    $description = $note !== ''
                        ? $note
                        : NonAcademicChargeTypeEnum::from($feeType)->label();

                    // Finance-owned intake: mint source_ref, price via GeneratorAmount,
                    // materialize charge + invoice line (no direct FinanceCharge::create).
                    $facts = [
                        'student_id' => $student->id,
                        'semester_id' => $semesterId,
                        'amount' => $amount,
                        'description' => $description,
                    ];
                    if (is_string($dueDate) && $dueDate !== '') {
                        $facts['due_date'] = $dueDate;
                    }

                    $result = $intake->request(new FinanceIntakeData(
                        source_system: FinanceOwnedObligationSource::SOURCE_SYSTEM,
                        source_kind: FinanceOwnedObligationSource::NON_ACADEMIC_BATCH,
                        source_ref: FinanceOwnedObligationSource::nonAcademicBatchRef(
                            $feeType,
                            $student->id,
                            $semesterId,
                        ),
                        financial_effect: FinancialEffect::Debit,
                        obligation_type: $feeType,
                        facts: $facts,
                    ));

                    $created[] = [
                        'student_code' => $code,
                        'charge_id' => $result->finance_charge_id,
                    ];

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
