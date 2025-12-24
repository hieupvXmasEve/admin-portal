<?php

namespace App\Actions\Form;

use App\Models\CourseRegistration;
use App\Models\FormTarget;
use App\Models\Student;
use App\Models\StudentFormAssignment;

class GenerateStudentAssignmentsAction
{
    /**
     * Generate assignments for a specific target when it becomes active and mandatory.
     */
    public function execute(FormTarget $target): void
    {
        if (! $target->is_mandatory) {
            return;
        }

        // Determine students based on scope
        $studentIds = match ($target->scope_type) {
            'course' => $this->getCourseStudents($target->scope_id),
            'semester' => $this->getSemesterStudents($target->scope_id, $target->campus_id),
            'department' => $this->getDepartmentStudents($target, $target->semester_id),
            'global' => $this->getAllActiveStudents(),
            default => collect(),
        };

        // Bulk insert (ignoring duplicates via insertOrIgnore or upsert)
        // For performance, chunking might be needed if thousands.

        $data = $studentIds->map(function ($studentId) use ($target) {
            return [
                'student_id' => $studentId,
                'form_target_id' => $target->id,
                'status' => 'not_started',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        foreach (array_chunk($data, 500) as $chunk) {
            StudentFormAssignment::upsert(
                $chunk,
                ['student_id', 'form_target_id'], // unique keys
                ['updated_at'] // update if exists
            );
        }
    }

    protected function getCourseStudents($offeringId)
    {
        return CourseRegistration::where('course_offering_id', $offeringId)
            ->whereIn('registration_status', ['pending', 'registered', 'confirmed', 'completed', 'failed'])
            ->pluck('student_id');
    }

    protected function getSemesterStudents($semesterId, $campusId = null)
    {
        // Active students in semester. Assuming 'enrollments' or 'course_registrations' linked to semester.
        // Or simply all students with status 'active'?
        // "Filter student cho từng context: context_type = semester: lấy student thuộc semester đó"
        // Usually implies students who have enrolled in AT LEAST ONE course in that semester.

        $query = CourseRegistration::whereHas('courseOffering', function ($q) use ($semesterId) {
            $q->where('semester_id', $semesterId);
        });

        if ($campusId) {
            $query->whereHas('student', function ($q) use ($campusId) {
                $q->where('campus_id', $campusId);
            });
        }

        return $query->distinct()
            ->pluck('student_id');
    }

    protected function getDepartmentStudents($target, $semesterId)
    {
        if ($semesterId) {
            // Students in this department AND active in this semester.
            // ... (keeping comments or simplifying if preferred, but user just wants the logic change)
            return $this->getSemesterStudents($semesterId, $target->campus_id);
        }

        return $this->getSemesterStudents($target->semester_id, $target->campus_id); // Fallback
    }

    protected function getAllActiveStudents()
    {
        return Student::where('status', ['intake_pre_uni_gc', 'intake_course'])->pluck('id');
    }
}
