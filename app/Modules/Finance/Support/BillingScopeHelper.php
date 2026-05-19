<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;

class BillingScopeHelper
{
    /**
     * Get the base query for eligible students based on scope parameters.
     *
     * @param  string  $scopeType  'all_eligible' | 'by_filter' | 'upload_list'
     * @param  array  $filters  ['program_id', 'enrollment_status', 'uploaded_student_ids']
     */
    public static function getEligibleStudentsQuery(int $semesterId, string $scopeType, array $filters = []): Builder
    {
        $campusId = null;
        try {
            $campusId = app('campus')?->id;
        } catch (\Exception $e) {
            // Campus binding might not exist in console/test env
        }

        $query = Student::query()
            ->select('students.*', 'programs.code as program_code')
            ->leftJoin('programs', 'students.program_id', '=', 'programs.id')
            ->whereIn('students.status', Student::FINANCIAL_STATUSES)
            ->when($campusId, fn ($q) => $q->where('students.campus_id', $campusId));

        // Eager load relationships needed for logic
        $query->with([
            'scholarshipAward.scholarshipDefinition',
            'voucherApplications' => fn ($q) => $q->where('semester_id', $semesterId),
            'invoices' => fn ($q) => $q->where('semester_id', $semesterId),
        ]);

        switch ($scopeType) {
            case 'by_filter':
                if (! empty($filters['program_id']) && $filters['program_id'] !== 'all') {
                    $query->where('students.program_id', $filters['program_id']);
                }
                if (! empty($filters['enrollment_status']) && $filters['enrollment_status'] !== 'all') {
                    // If specific status requested (though base query limits to FINANCIAL_STATUSES)
                    // If 'active' vs 'enrolled' distinction exists, apply here.
                    // taking 'active' as generic for FINANCIAL_STATUSES
                }
                break;

            case 'upload_list':
                if (! empty($filters['uploaded_student_ids'])) {
                    $query->whereIn('students.student_id', $filters['uploaded_student_ids']);
                }
                break;

            case 'all_eligible':
            default:
                // No extra filters needed beyond base FINANCIAL_STATUSES + Campus
                break;
        }

        return $query;
    }
}
