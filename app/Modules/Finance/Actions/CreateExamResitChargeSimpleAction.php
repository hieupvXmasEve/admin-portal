<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\ExamResitAttempt;
use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tạo FinanceCharge + invoice line cho nguồn thi lại (exam_resit_fee) — không tạo DNG.
 * Dùng khi HQ ghi nhận khoản phí cho một ExamResitAttempt đã được Academic duyệt và
 * đang chờ HQ tạo phí (hq_fee_status = hq_fee_pending).
 *
 * Prefer FinanceIntakeContract for new work. Legacy simple create still keys by
 * source_type/source_id; intake idempotency now lives on finance_obligations.
 *
 * Academic là chủ nguồn; HQ/Finance là chủ việc tạo phí và theo dõi thanh toán.
 *
 * @see CreateRetakeCourseChargeSimpleAction cho luồng học lại (HL) tương đương.
 */
class CreateExamResitChargeSimpleAction
{
    public function __construct(
        protected CreateFinanceChargeAction $createChargeAction,
    ) {}

    /**
     * @param  array{
     *   attempt_id: int,
     *   amount?: float|null,
     *   due_date?: string|null,
     * }  $data
     */
    public function handle(array $data): ExamResitAttempt
    {
        return DB::transaction(function () use ($data) {
            $attempt = ExamResitAttempt::lockForUpdate()->findOrFail($data['attempt_id']);

            if ($attempt->hq_fee_status !== ExamResitAttempt::HQ_FEE_PENDING) {
                throw ValidationException::withMessages([
                    'attempt_id' => ['Chỉ có thể tạo phí cho nguồn thi lại đang chờ HQ tạo phí.'],
                ]);
            }

            $unit = $attempt->unit;
            $amount = $data['amount'] ?? (float) $attempt->fee_amount;

            $charge = $this->findActiveSourceCharge($attempt)
                ?? $this->createChargeAction->handle([
                    'student_id' => $attempt->student_id,
                    'semester_id' => $attempt->charge_semester_id,
                    'charge_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
                    'amount' => $amount,
                    'description' => "Phí thi lại: {$unit?->code} - {$unit?->name}",
                    'source_type' => ExamResitAttempt::class,
                    'source_id' => $attempt->id,
                    'created_by_user_id' => auth()->id(),
                    'due_date' => $data['due_date'] ?? null,
                ]);

            $attempt->transitionToChargeCreated($charge->id, (int) auth()->id());

            return $attempt->fresh();
        });
    }

    private function findActiveSourceCharge(ExamResitAttempt $attempt): ?FinanceCharge
    {
        return FinanceCharge::query()
            ->where('source_type', ExamResitAttempt::class)
            ->where('source_id', $attempt->id)
            ->where('charge_type', FinanceCharge::TYPE_EXAM_RESIT_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->first();
    }
}
