<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Operations;

use App\Enums\StudentActionType;
use App\Models\CourseRegistration;
use App\Models\DeferCase;
use App\Models\FinanceCharge;
use App\Models\StudentActionLog;
use Illuminate\Support\Facades\DB;

class GetBillingExceptionCountsQuery
{
    public function handle(?int $semesterId): array
    {
        $campusId = app('campus')?->id;

        return [
            'missing_charge' => $this->countMissingChargeExceptions($semesterId, $campusId),
            'retake_no_charge' => $this->countRetakeNoChargeExceptions($semesterId, $campusId),
            'defer_no_case' => $this->countDeferNoCaseExceptions($semesterId, $campusId),
            'mismatch' => 0, // To be implemented
        ];
    }

    private function countMissingChargeExceptions(?int $semesterId, ?int $campusId): int
    {
        return CourseRegistration::query()
            ->when($campusId, fn($q) => $q->whereHas('student', fn($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn($q) => $q->whereHas('courseOffering', fn($q) => $q->where('semester_id', $semesterId)))
            ->whereNotIn('registration_status', ['dropped', 'withdrawn'])
            ->whereNotExists(function ($query) use ($semesterId) {
                $query->select(DB::raw(1))
                    ->from('finance_charges')
                    ->whereColumn('finance_charges.student_id', 'course_registrations.student_id')
                    ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE);
                if ($semesterId) {
                    $query->where('finance_charges.semester_id', $semesterId);
                }
            })
            ->distinct('student_id')
            ->count('student_id');
    }

    private function countRetakeNoChargeExceptions(?int $semesterId, ?int $campusId): int
    {
        return CourseRegistration::where('is_retake', true)
            ->when($campusId, fn($q) => $q->whereHas('student', fn($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn($q) => $q->whereHas('courseOffering', fn($q) => $q->where('semester_id', $semesterId)))
            ->whereNotIn('registration_status', ['dropped', 'withdrawn'])
            ->whereNotExists(function ($query) use ($semesterId) {
                $query->select(DB::raw(1))
                    ->from('finance_charges')
                    ->whereColumn('finance_charges.student_id', 'course_registrations.student_id')
                    ->where('finance_charges.charge_type', FinanceCharge::TYPE_RETAKE_FEE)
                    ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE);
                if ($semesterId) {
                    $query->where('finance_charges.semester_id', $semesterId);
                }
            })
            ->count();
    }

    private function countDeferNoCaseExceptions(?int $semesterId, ?int $campusId): int
    {
        // action_type is string in DB or Enum cast? Check usage. 
        // In previous controller: StudentActionType::ACADEMIC_DEFER->value.
        return StudentActionLog::where('action_type', StudentActionType::ACADEMIC_DEFER->value)
            ->when($campusId, fn($q) => $q->whereHas('student', fn($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn($q) => $q->where('from_semester_id', $semesterId))
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('defer_cases')
                    ->whereColumn('defer_cases.student_action_log_id', 'student_action_logs.id');
            })
            ->count();
    }
}
