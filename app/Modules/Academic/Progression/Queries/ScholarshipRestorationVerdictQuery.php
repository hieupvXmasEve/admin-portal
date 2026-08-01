<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Models\AcademicRecord;
use App\Models\AssessmentComponentDetailScore;
use App\Shared\Contracts\Academic\ScholarshipRestorationVerdictReader;

/**
 * Restoration verdict for one student's target semester (Phase 5). Reuses
 * the P3 candidate criteria (ScholarshipAdjustmentCandidateQuery): non-EGC,
 * intake-course-scoped records, is_passed=false non-NULL, grade_finalized_date
 * set, override_pass=false, component-level pending-appeal exclusion.
 *
 * Completeness precondition: EVERY non-EGC record for the student in the
 * semester must be finalized (grade_finalized_date set) and free of a
 * pending component appeal, or the verdict is NOT_FINALIZED — a partial
 * result set must never be read as "clean". A record with a NULL is_passed
 * (not yet evaluated) is likewise treated as incomplete data, not clean.
 */
class ScholarshipRestorationVerdictQuery implements ScholarshipRestorationVerdictReader
{
    public function verdict(int $studentId, int $semesterId): string
    {
        // Reuses the P3 record-level criteria (non-EGC, is_passed non-null,
        // override_pass) but DELIBERATELY omits P3's `student.status =
        // intake_course` filter: the verdict runs AFTER the target semester,
        // by which time the student has usually progressed past intake_course —
        // filtering on it would leave every progressed student permanently
        // NOT_FINALIZED and never restorable. Student identity is already fixed
        // by the adjustment; only the semester's grade records matter here.
        $records = AcademicRecord::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->whereHas('unit', fn ($query) => $query->where('unit_type', '!=', 'egc'))
            ->get(['id', 'student_id', 'course_offering_id', 'is_passed', 'override_pass', 'grade_finalized_date']);

        // No qualifying enrollment at all — nothing to evaluate; default-deny
        // rather than silently treating an empty set as clean.
        if ($records->isEmpty()) {
            return self::NOT_FINALIZED;
        }

        $incomplete = $records->contains(
            fn (AcademicRecord $record) => $record->grade_finalized_date === null || $record->is_passed === null,
        );

        if ($incomplete) {
            return self::NOT_FINALIZED;
        }

        $offeringIds = $records->pluck('course_offering_id')->unique()->values();

        $hasPendingAppeal = AssessmentComponentDetailScore::query()
            ->where('student_id', $studentId)
            ->where('appeal_status', 'pending')
            ->whereIn('course_offering_id', $offeringIds)
            ->exists();

        if ($hasPendingAppeal) {
            return self::NOT_FINALIZED;
        }

        $stillFailing = $records->contains(
            fn (AcademicRecord $record) => $record->is_passed === false && $record->override_pass === false,
        );

        return $stillFailing ? self::STILL_FAILING : self::CLEAN;
    }
}
