<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Shared\Contracts\Finance\DTO\ObligationSettlementResult;
use App\Shared\Contracts\Finance\ObligationSettlementReader;

/**
 * Academic-side helper: resolve settlement for retake/resit sources via the
 * Finance ObligationSettlementReader contract (no Finance model imports).
 */
final class AcademicObligationSettlement
{
    public function __construct(
        private readonly ObligationSettlementReader $reader,
    ) {}

    public function forRetake(CourseRetakeRegistration|int $registration): ObligationSettlementResult
    {
        $id = $registration instanceof CourseRetakeRegistration ? (int) $registration->id : $registration;

        return $this->reader->getSettlement(
            AcademicFinanceObligationSource::SOURCE_SYSTEM,
            AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
            AcademicFinanceObligationSource::courseRetakeRegistrationRef($id),
            AcademicFinanceObligationSource::RETAKE_FEE,
        );
    }

    public function forExamResit(ExamResitAttempt|int $attempt): ObligationSettlementResult
    {
        $id = $attempt instanceof ExamResitAttempt ? (int) $attempt->id : $attempt;

        return $this->reader->getSettlement(
            AcademicFinanceObligationSource::SOURCE_SYSTEM,
            AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT,
            AcademicFinanceObligationSource::examResitAttemptRef($id),
            AcademicFinanceObligationSource::EXAM_RESIT_FEE,
        );
    }

    public function isRetakeSettled(CourseRetakeRegistration|int $registration): bool
    {
        $id = $registration instanceof CourseRetakeRegistration ? (int) $registration->id : $registration;

        // Ledger-only — used by sync/auto-enroll side effects.
        return $this->reader->isSettled(
            AcademicFinanceObligationSource::SOURCE_SYSTEM,
            AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
            AcademicFinanceObligationSource::courseRetakeRegistrationRef($id),
            AcademicFinanceObligationSource::RETAKE_FEE,
        );
    }

    public function isExamResitSettled(ExamResitAttempt|int $attempt): bool
    {
        $id = $attempt instanceof ExamResitAttempt ? (int) $attempt->id : $attempt;

        return $this->reader->isSettled(
            AcademicFinanceObligationSource::SOURCE_SYSTEM,
            AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT,
            AcademicFinanceObligationSource::examResitAttemptRef($id),
            AcademicFinanceObligationSource::EXAM_RESIT_FEE,
        );
    }

    /**
     * Display/cancel paid evidence (ledger or paid DNG before bridge).
     */
    public function hasRetakePaidEvidence(CourseRetakeRegistration|int $registration): bool
    {
        $id = $registration instanceof CourseRetakeRegistration ? (int) $registration->id : $registration;

        return $this->reader->isSettledOrExternallyPaid(
            AcademicFinanceObligationSource::SOURCE_SYSTEM,
            AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION,
            AcademicFinanceObligationSource::courseRetakeRegistrationRef($id),
            AcademicFinanceObligationSource::RETAKE_FEE,
        );
    }

    public function hasExamResitPaidEvidence(ExamResitAttempt|int $attempt): bool
    {
        $id = $attempt instanceof ExamResitAttempt ? (int) $attempt->id : $attempt;

        return $this->reader->isSettledOrExternallyPaid(
            AcademicFinanceObligationSource::SOURCE_SYSTEM,
            AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT,
            AcademicFinanceObligationSource::examResitAttemptRef($id),
            AcademicFinanceObligationSource::EXAM_RESIT_FEE,
        );
    }

    public function hasUnsettledRetakeObligation(CourseRetakeRegistration $registration): bool
    {
        return $this->isUnsettledPayable($this->forRetake($registration));
    }

    public function hasUnsettledExamResitObligation(ExamResitAttempt $attempt): bool
    {
        return $this->isUnsettledPayable($this->forExamResit($attempt));
    }

    private function isUnsettledPayable(ObligationSettlementResult $settlement): bool
    {
        // finance_obligation_id may be null on legacy morph-backed charges; unpaid
        // ledger state is enough to treat the source as having an unsettled payable.
        return ! $settlement->isSettled()
            && in_array($settlement->settlement_state, [
                ObligationSettlementResult::STATE_UNPAID,
                ObligationSettlementResult::STATE_PARTIALLY_PAID,
            ], true);
    }
}
