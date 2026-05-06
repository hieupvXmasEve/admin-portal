<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\AcademicRecord;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\Student;
use Illuminate\Support\Collection;

class ListRetakeCourseEligibleStudentsQuery
{
    /**
     * Return eligible students for retake course registration.
     *
     * Eligibility:
     * 1. Student.status = 'intake_course'
     * 2. Has AcademicRecord with is_passed = false, completion_status finalized (not in_progress),
     *    and override_pass = false (not overridden to pass)
     * 3. That unit has a CourseOffering open (is_active=true, enrollment_status='open') in the target semester
     * 4. No existing non-terminal course_retake_registrations for same student+unit+semester
     *
     * @param  array{
     *   campus_id?: int|null,
     *   semester_id?: int|null,
     *   search?: string|null,
     *   unit_id?: int|null,
     * }  $filters
     * @return Collection<int, array{
     *   student: Student,
     *   failed_records: Collection,
     *   available_offerings: Collection,
     * }>
     */
    public function handle(array $filters): Collection
    {
        $semesterId = $filters['semester_id'] ?? null;
        $campusId = $filters['campus_id'] ?? null;
        $search = $filters['search'] ?? null;
        $unitId = $filters['unit_id'] ?? null;

        // Get students with intake_course status who have failed academic records
        $query = Student::query()
            ->where('status', 'intake_course')
            ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
            ->when($search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%");
                });
            })
            ->whereHas('academicRecords', function ($q) use ($unitId) {
                $q->where('is_passed', false)
                    ->where('completion_status', '!=', 'in_progress')
                    ->where(fn ($q) => $q->where('override_pass', false)->orWhereNull('override_pass'));
                if ($unitId) {
                    $q->where('unit_id', $unitId);
                }
            });

        $students = $query->with([
            'campus',
            'program',
        ])->get();

        $results = collect();

        foreach ($students as $student) {
            // Get failed academic records for units in student's curriculum version
            // Uses is_passed + override_pass instead of completion_status for pass/fail logic
            $failedRecords = AcademicRecord::query()
                ->where('student_id', $student->id)
                ->where('is_passed', false)
                ->where('completion_status', '!=', 'in_progress')
                ->where(fn ($q) => $q->where('override_pass', false)->orWhereNull('override_pass'))
                ->when($unitId, fn ($q) => $q->where('unit_id', $unitId))
                ->whereIn('unit_id', function ($subQuery) use ($student) {
                    $subQuery->select('unit_id')
                        ->from('curriculum_units')
                        ->where('curriculum_version_id', $student->curriculum_version_id);
                })
                ->with('unit')
                ->get();

            foreach ($failedRecords as $record) {
                // Check if there's already an active retake registration for this student+unit+semester
                $hasActiveRegistration = CourseRetakeRegistration::query()
                    ->where('student_id', $student->id)
                    ->where('unit_id', $record->unit_id)
                    ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
                    ->nonTerminal()
                    ->exists();

                if ($hasActiveRegistration) {
                    continue;
                }

                // Find available course offerings for this unit.
                // Use unit_id directly — curriculum_unit_id may be NULL on legacy offerings.
                // Exclude only cancelled; completed offerings with enrollment_status=open are valid for retake.
                $offeringsQuery = CourseOffering::query()
                    ->where('is_active', true)
                    ->where('enrollment_status', 'open')
                    ->whereNotIn('course_status', ['completed', 'cancelled'])
                    ->where('unit_id', $record->unit_id)
                    ->when($semesterId, fn ($q) => $q->where('semester_id', $semesterId))
                    ->when($campusId, fn ($q) => $q->where('campus_id', $campusId));

                $offerings = $offeringsQuery->with(['semester', 'campus', 'lecture'])->get();

                if ($offerings->isEmpty()) {
                    continue;
                }

                $results->push([
                    'student' => $student,
                    'failed_record' => $record,
                    'unit' => $record->unit,
                    'available_offerings' => $offerings,
                ]);
            }
        }

        return $results;
    }
}
