<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Shared\Contracts\Finance\DTO\FinanceCancellationCompletionData;
use App\Shared\Contracts\Finance\Enums\FinanceCancellationFeeDisposition;
use App\Shared\Contracts\Finance\FinanceCancellationCompletionContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Source-side consumer for Finance Cancellation Operation completion events.
 * Terminalizes Academic source rows only after Finance commits effects and
 * publishes the durable outbox event. Idempotent under redelivery; also upgrades
 * fee disposition when late paid evidence arrives after terminal cancel.
 */
class CompleteFinanceCancellationOperationAction implements FinanceCancellationCompletionContract
{
    public function __construct(
        private readonly SendExamResitCancellationNoticeAction $sendExamResitCancellationNoticeAction,
    ) {}

    public function complete(FinanceCancellationCompletionData $completion): void
    {
        if ($completion->sourceSystem !== AcademicFinanceObligationSource::SOURCE_SYSTEM) {
            return;
        }

        match ($completion->sourceKind) {
            AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION => $this->completeRetake($completion),
            AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT => $this->completeExamResit($completion),
            default => null,
        };
    }

    /** @param array{completion: FinanceCancellationCompletionData} $data */
    public static function run(array $data): void
    {
        app(self::class)->complete($data['completion']);
    }

    private function completeRetake(FinanceCancellationCompletionData $completion): void
    {
        DB::transaction(function () use ($completion): void {
            $registration = CourseRetakeRegistration::query()
                ->lockForUpdate()
                ->find($this->sourceId($completion->sourceRef));

            if ($registration === null) {
                return;
            }

            if ($registration->status === CourseRetakeRegistration::STATUS_CANCELLED) {
                // Late paid disposition after terminal cancel.
                if ((bool) ($completion->resultPayload['is_paid'] ?? false)
                    && $registration->hq_fee_status !== CourseRetakeRegistration::HQ_FEE_PAID) {
                    $registration->update(['hq_fee_status' => CourseRetakeRegistration::HQ_FEE_PAID]);
                }

                return;
            }

            if ($registration->status !== CourseRetakeRegistration::STATUS_FINANCE_PENDING_CANCELLATION) {
                return;
            }

            $registration->update([
                'status' => CourseRetakeRegistration::STATUS_CANCELLED,
                'hq_fee_status' => (bool) ($completion->resultPayload['is_paid'] ?? false)
                    ? CourseRetakeRegistration::HQ_FEE_PAID
                    : CourseRetakeRegistration::HQ_FEE_CANCELLED,
                'cancelled_at' => now(),
            ]);
        });
    }

    private function completeExamResit(FinanceCancellationCompletionData $completion): void
    {
        $attempt = DB::transaction(function () use ($completion): ?ExamResitAttempt {
            $attempt = ExamResitAttempt::query()
                ->lockForUpdate()
                ->find($this->sourceId($completion->sourceRef));

            if ($attempt === null) {
                return null;
            }

            $wasPaid = (bool) ($completion->resultPayload['is_paid'] ?? false);
            $disposition = $this->resolveFeeDisposition($completion->resultPayload, $wasPaid);

            if ($attempt->status === ExamResitAttempt::STATUS_CANCELLED) {
                if ($wasPaid && (
                    $attempt->hq_fee_status !== ExamResitAttempt::HQ_FEE_PAID
                    || $attempt->cancellation_fee_disposition !== ExamResitAttempt::CANCELLATION_FEE_KEPT_PAID_NO_REFUND
                )) {
                    $attempt->update([
                        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PAID,
                        'cancellation_fee_disposition' => ExamResitAttempt::CANCELLATION_FEE_KEPT_PAID_NO_REFUND,
                    ]);
                }

                return null;
            }

            if ($attempt->status !== ExamResitAttempt::STATUS_FINANCE_PENDING_CANCELLATION) {
                return null;
            }

            $previousSessionId = $attempt->exam_resit_session_id;

            $attempt->update([
                'status' => ExamResitAttempt::STATUS_CANCELLED,
                'exam_resit_session_id' => null,
                'cancelled_at' => now(),
                'hq_fee_status' => $wasPaid
                    ? ExamResitAttempt::HQ_FEE_PAID
                    : ExamResitAttempt::HQ_FEE_CANCELLED,
                'cancellation_fee_disposition' => $disposition,
            ]);

            if ($previousSessionId !== null) {
                $this->refreshSessionCandidateCount($previousSessionId);
            }

            return $attempt->fresh();
        });

        if ($attempt instanceof ExamResitAttempt) {
            $this->sendExamResitCancellationNotice($attempt);
        }
    }

    /** @param array<string, mixed> $payload */
    private function resolveFeeDisposition(array $payload, bool $wasPaid): string
    {
        $explicit = $payload['fee_disposition'] ?? null;
        if (is_string($explicit) && $explicit !== '') {
            return match ($explicit) {
                FinanceCancellationFeeDisposition::KeptPaidNoRefund->value => ExamResitAttempt::CANCELLATION_FEE_KEPT_PAID_NO_REFUND,
                FinanceCancellationFeeDisposition::VoidedUnpaidCharge->value => ExamResitAttempt::CANCELLATION_FEE_VOIDED_UNPAID_CHARGE,
                FinanceCancellationFeeDisposition::NoCharge->value => ExamResitAttempt::CANCELLATION_FEE_NO_CHARGE,
                default => $wasPaid
                    ? ExamResitAttempt::CANCELLATION_FEE_KEPT_PAID_NO_REFUND
                    : ExamResitAttempt::CANCELLATION_FEE_VOIDED_UNPAID_CHARGE,
            };
        }

        if ($wasPaid) {
            return ExamResitAttempt::CANCELLATION_FEE_KEPT_PAID_NO_REFUND;
        }

        if (($payload['had_unpaid_charge'] ?? false) === true || ($payload['finance_charge_id'] ?? null) !== null) {
            return ExamResitAttempt::CANCELLATION_FEE_VOIDED_UNPAID_CHARGE;
        }

        return ExamResitAttempt::CANCELLATION_FEE_NO_CHARGE;
    }

    private function refreshSessionCandidateCount(int $sessionId): void
    {
        $count = ExamResitAttempt::query()
            ->where('exam_resit_session_id', $sessionId)
            ->whereIn('status', [
                ExamResitAttempt::STATUS_SCHEDULED,
                ExamResitAttempt::STATUS_COMPLETED,
                ExamResitAttempt::STATUS_NO_SHOW,
            ])
            ->count();

        DB::table('exam_resit_sessions')
            ->where('id', $sessionId)
            ->update(['actual_candidates' => $count]);
    }

    private function sendExamResitCancellationNotice(ExamResitAttempt $attempt): void
    {
        try {
            $this->sendExamResitCancellationNoticeAction->run($attempt);

            ExamResitAttempt::query()
                ->whereKey($attempt->id)
                ->update([
                    'cancellation_notice_sent_at' => now(),
                    // EmailLog is created by the V2 delivery worker, after this
                    // source-side operation commits. The legacy column remains
                    // nullable for history compatibility.
                    'cancellation_notice_email_log_id' => null,
                    'cancellation_notice_error' => null,
                ]);
        } catch (\Throwable $exception) {
            ExamResitAttempt::query()
                ->whereKey($attempt->id)
                ->update([
                    'cancellation_notice_error' => mb_substr($exception->getMessage(), 0, 2000),
                ]);

            Log::warning('Failed to queue exam resit cancellation notice', [
                'exam_resit_attempt_id' => $attempt->id,
                'student_id' => $attempt->student_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sourceId(string $sourceRef): int
    {
        return (int) (string) str($sourceRef)->afterLast(':');
    }
}
