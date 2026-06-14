<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Enums\StudentActionType;
use App\Models\CourseRegistration;
use App\Models\FinanceCharge;
use App\Models\StudentActionLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BillingExceptionCollector
{
    public function collect(?int $semesterId, ?string $type, ?int $campusId): Collection
    {
        $rows = collect();

        if ($type === null || $type === 'all' || $type === 'missing_charge') {
            $rows = $rows->merge($this->missingChargeRows($semesterId, $campusId));
        }

        if ($type === null || $type === 'all' || $type === 'retake_no_charge') {
            $rows = $rows->merge($this->retakeNoChargeRows($semesterId, $campusId));
        }

        if ($type === null || $type === 'all' || $type === 'defer_no_case') {
            $rows = $rows->merge($this->deferNoCaseRows($semesterId, $campusId));
        }

        return $rows->sortByDesc('created_at')->values();
    }

    public function counts(?int $semesterId, ?int $campusId): array
    {
        return [
            'missing_charge' => $this->missingChargeRows($semesterId, $campusId)->count(),
            'retake_no_charge' => $this->retakeNoChargeRows($semesterId, $campusId)->count(),
            'defer_no_case' => $this->deferNoCaseRows($semesterId, $campusId)->count(),
            'mismatch' => 0,
        ];
    }

    private function missingChargeRows(?int $semesterId, ?int $campusId): Collection
    {
        return CourseRegistration::query()
            ->with(['student:id,student_id,full_name', 'courseOffering.unit'])
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn ($q) => $q->whereHas('courseOffering', fn ($cq) => $cq->where('semester_id', $semesterId)))
            ->whereNotIn('registration_status', ['dropped', 'withdrawn'])
            ->whereNotExists(function ($query) use ($semesterId) {
                $query->select(DB::raw(1))
                    ->from('finance_charges')
                    ->whereColumn('finance_charges.student_id', 'course_registrations.student_id')
                    ->whereColumn('finance_charges.semester_id', 'course_registrations.semester_id')
                    ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE);
                if ($semesterId) {
                    $query->where('finance_charges.semester_id', $semesterId);
                }
            })
            ->get()
            ->unique('student_id')
            ->map(fn (CourseRegistration $registration) => [
                'type' => 'missing_charge',
                'source_id' => $registration->id,
                'student_id' => $registration->student_id,
                'student_code' => $registration->student?->student_id,
                'student_name' => $registration->student?->full_name,
                'description' => 'Sinh viên đăng ký học nhưng chưa có phí active trong học kỳ',
                'severity' => 'high',
                'context' => [
                    'course_registration_id' => $registration->id,
                    'semester_id' => $semesterId,
                ],
                'created_at' => $registration->created_at?->toISOString() ?? now()->toISOString(),
                'fixable' => true,
            ]);
    }

    private function retakeNoChargeRows(?int $semesterId, ?int $campusId): Collection
    {
        return CourseRegistration::query()
            ->with(['student:id,student_id,full_name', 'courseOffering.unit'])
            ->where('is_retake', true)
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn ($q) => $q->whereHas('courseOffering', fn ($cq) => $cq->where('semester_id', $semesterId)))
            ->whereNotIn('registration_status', ['dropped', 'withdrawn'])
            ->whereNotExists(function ($query) use ($semesterId) {
                $query->select(DB::raw(1))
                    ->from('finance_charges')
                    ->whereColumn('finance_charges.student_id', 'course_registrations.student_id')
                    ->whereColumn('finance_charges.semester_id', 'course_registrations.semester_id')
                    ->where('finance_charges.charge_type', FinanceCharge::TYPE_RETAKE_FEE)
                    ->where('finance_charges.status', FinanceCharge::STATUS_ACTIVE)
                    ->where('finance_charges.source_type', CourseRegistration::class)
                    ->whereColumn('finance_charges.source_id', 'course_registrations.id');
                if ($semesterId) {
                    $query->where('finance_charges.semester_id', $semesterId);
                }
            })
            ->get()
            ->map(fn (CourseRegistration $registration) => [
                'type' => 'retake_no_charge',
                'source_id' => $registration->id,
                'student_id' => $registration->student_id,
                'student_code' => $registration->student?->student_id,
                'student_name' => $registration->student?->full_name,
                'description' => 'Sinh viên học lại nhưng chưa có phí retake active',
                'severity' => 'medium',
                'context' => [
                    'course_registration_id' => $registration->id,
                    'semester_id' => $semesterId,
                ],
                'created_at' => $registration->created_at?->toISOString() ?? now()->toISOString(),
                'fixable' => true,
            ]);
    }

    private function deferNoCaseRows(?int $semesterId, ?int $campusId): Collection
    {
        return StudentActionLog::query()
            ->with(['student:id,student_id,full_name'])
            ->where('action_type', StudentActionType::ACADEMIC_DEFER->value)
            ->when($campusId, fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('campus_id', $campusId)))
            ->when($semesterId, fn ($q) => $q->where('from_semester_id', $semesterId))
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('defer_cases')
                    ->whereColumn('defer_cases.student_action_log_id', 'student_action_logs.id');
            })
            ->get()
            ->map(fn (StudentActionLog $log) => [
                'type' => 'defer_no_case',
                'source_id' => $log->id,
                'student_id' => $log->student_id,
                'student_code' => $log->student?->student_id,
                'student_name' => $log->student?->full_name,
                'description' => 'Defer action ghi nhận nhưng chưa có defer_case',
                'severity' => 'medium',
                'context' => [
                    'student_action_log_id' => $log->id,
                    'from_semester_id' => $log->from_semester_id,
                ],
                'created_at' => $log->created_at?->toISOString() ?? now()->toISOString(),
                'fixable' => false,
            ]);
    }
}
