<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Student;
use App\Models\Semester;
use App\Models\Enrollment;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AutomatedEnrollmentService
{
    /**
     * Complete automated enrollment for new students following SemesterEnrollmentController patterns
     */
    public function processAutomatedEnrollment(array $studentIds, int $semesterId, int $campusId): array
    {
        return DB::transaction(function () use ($studentIds, $semesterId, $campusId) {
            $results = [
                'enrollments' => ['created' => 0, 'errors' => [], 'skipped' => 0],
                'course_offerings' => ['created' => 0, 'errors' => [], 'existing' => 0],
                'course_registrations' => ['created' => 0, 'errors' => [], 'skipped' => 0],
                'summary' => []
            ];

            // Step 1: Create semester enrollments
            $enrollmentResult = $this->createEnrollments($studentIds, $semesterId, $campusId);
            $results['enrollments'] = $enrollmentResult;

            // Step 2: Get suggested courses and create offerings
            $courseOfferingResult = $this->createCourseOfferings($studentIds, $semesterId, $campusId);
            $results['course_offerings'] = $courseOfferingResult;

            // Step 3: Register students for courses
            $registrationResult = $this->registerStudentsForCourses($studentIds, $semesterId, $campusId);
            $results['course_registrations'] = $registrationResult;

            // Generate summary
            $results['summary'] = $this->generateSummary($results, count($studentIds));

            Log::info('Automated enrollment completed', [
                'total_students' => count($studentIds),
                'semester_id' => $semesterId,
                'campus_id' => $campusId,
                'results' => $results['summary']
            ]);

            return $results;
        });
    }

    /**
     * Step 1: Create enrollments following SemesterEnrollmentController pattern
     */
    private function createEnrollments(array $studentIds, int $semesterId, int $campusId): array
    {
        $result = ['created' => 0, 'errors' => [], 'skipped' => 0];

        foreach ($studentIds as $studentId) {
            try {
                $student = Student::with('curriculumVersion')->findOrFail($studentId);

                // Skip if student doesn't have curriculum version
                if (!$student->curriculum_version_id) {
                    $result['errors'][] = "Student {$student->student_id} has no curriculum version assigned";
                    $result['skipped']++;
                    continue;
                }

                // Check if enrollment already exists
                $existingEnrollment = Enrollment::where('student_id', $studentId)
                    ->where('semester_id', $semesterId)
                    ->first();

                if ($existingEnrollment) {
                    $result['skipped']++;
                    continue;
                }

                // Determine semester number (following SemesterEnrollmentController logic)
                $latestEnrollment = Enrollment::where('student_id', $studentId)
                    ->orderBy('semester_number', 'desc')
                    ->first();

                $semesterNumber = $latestEnrollment ? $latestEnrollment->semester_number + 1 : 1;

                // Validate semester number doesn't exceed reasonable limits
                if ($semesterNumber > 8) {
                    $result['errors'][] = "Student {$student->student_id} has exceeded maximum semester limit";
                    $result['skipped']++;
                    continue;
                }

                Enrollment::create([
                    'student_id' => $studentId,
                    'semester_id' => $semesterId,
                    'curriculum_version_id' => $student->curriculum_version_id,
                    'semester_number' => $semesterNumber,
                    'status' => 'in_progress',
                ]);

                $result['created']++;
            } catch (Exception $e) {
                $result['errors'][] = "Failed to enroll student ID {$studentId}: {$e->getMessage()}";
                Log::error('Enrollment creation failed', [
                    'student_id' => $studentId,
                    'semester_id' => $semesterId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Step 2: Create course offerings based on curriculum units
     */
    private function createCourseOfferings(array $studentIds, int $semesterId, int $campusId): array
    {
        $result = ['created' => 0, 'errors' => [], 'existing' => 0];

        try {
            // Get enrollments for these students in this semester
            $enrollments = Enrollment::where('semester_id', $semesterId)
                ->whereIn('student_id', $studentIds)
                ->where('status', 'in_progress')
                ->with(['student.curriculumVersion', 'curriculumVersion'])
                ->get();

            if ($enrollments->isEmpty()) {
                $result['errors'][] = 'No enrollments found for students';
                return $result;
            }

            // Group enrollments by curriculum_version_id and semester_number
            $enrollmentGroups = $enrollments->groupBy(function ($enrollment) {
                return $enrollment->curriculum_version_id . '_' . $enrollment->semester_number;
            });

            $curriculumUnitDemand = [];

            foreach ($enrollmentGroups as $group) {
                $firstEnrollment = $group->first();
                $curriculumVersionId = $firstEnrollment->curriculum_version_id;
                $semesterNumber = $firstEnrollment->semester_number;
                $studentCount = $group->count();

                // Get curriculum units for this group
                $curriculumUnits = CurriculumUnit::where('curriculum_version_id', $curriculumVersionId)
                    ->where('semester_number', $semesterNumber)
                    ->with('unit')
                    ->get();

                foreach ($curriculumUnits as $curriculumUnit) {
                    $curriculumUnitId = $curriculumUnit->id;

                    if (!isset($curriculumUnitDemand[$curriculumUnitId])) {
                        $curriculumUnitDemand[$curriculumUnitId] = $studentCount;
                    } else {
                        $curriculumUnitDemand[$curriculumUnitId] += $studentCount;
                    }
                }
            }

            // Create course offerings for curriculum units with demand
            foreach ($curriculumUnitDemand as $curriculumUnitId => $estimatedStudents) {
                try {
                    // Check if offering already exists
                    $existingOffering = CourseOffering::where('semester_id', $semesterId)
                        ->where('curriculum_unit_id', $curriculumUnitId)
                        ->first();

                    if ($existingOffering) {
                        $result['existing']++;
                        continue;
                    }

                    // Calculate capacity based on estimated students (with 20% buffer)
                    $maxCapacity = max(30, ceil($estimatedStudents * 1.2));

                    CourseOffering::create([
                        'semester_id' => $semesterId,
                        'curriculum_unit_id' => $curriculumUnitId,
                        'max_capacity' => $maxCapacity,
                        'current_enrollment' => 0,
                        'waitlist_capacity' => 10,
                        'current_waitlist' => 0,
                        'delivery_mode' => 'in_person',
                        'is_active' => true,
                        'enrollment_status' => 'open',
                    ]);

                    $result['created']++;
                } catch (Exception $e) {
                    $result['errors'][] = "Failed to create offering for curriculum unit ID {$curriculumUnitId}: {$e->getMessage()}";
                    Log::error('Course offering creation failed', [
                        'semester_id' => $semesterId,
                        'curriculum_unit_id' => $curriculumUnitId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (Exception $e) {
            $result['errors'][] = "Failed to process course offerings: {$e->getMessage()}";
            Log::error('Course offering processing failed', [
                'semester_id' => $semesterId,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Step 3: Register students for courses following SemesterEnrollmentController pattern
     */
    private function registerStudentsForCourses(array $studentIds, int $semesterId, int $campusId): array
    {
        $result = ['created' => 0, 'errors' => [], 'skipped' => 0];

        // Get all enrollments for this semester that are in progress
        $enrollments = Enrollment::where('semester_id', $semesterId)
            ->whereIn('student_id', $studentIds)
            ->where('status', 'in_progress')
            ->with(['student', 'curriculumVersion'])
            ->get();

        foreach ($enrollments as $enrollment) {
            try {
                $student = $enrollment->student;

                // Skip inactive students
                if ($student->status !== 'active') {
                    $result['skipped']++;
                    continue;
                }

                // Get curriculum units for this enrollment
                $curriculumUnits = CurriculumUnit::where('curriculum_version_id', $enrollment->curriculum_version_id)
                    ->where('semester_number', $enrollment->semester_number)
                    ->with('unit')
                    ->get();

                if ($curriculumUnits->isEmpty()) {
                    $result['errors'][] = "No curriculum units found for student {$student->student_id} in semester {$enrollment->semester_number}";
                    $result['skipped']++;
                    continue;
                }

                foreach ($curriculumUnits as $curriculumUnit) {
                    try {
                        // Find available course offering for this unit
                        $courseOffering = CourseOffering::where('semester_id', $semesterId)
                            ->where('curriculum_unit_id', $curriculumUnit->id)
                            ->where('is_active', true)
                            ->where('enrollment_status', 'open')
                            ->first();

                        if (!$courseOffering) {
                            continue;
                        }

                        // Check if student is already registered
                        $existingRegistration = CourseRegistration::where('student_id', $student->id)
                            ->where('course_offering_id', $courseOffering->id)
                            ->where('semester_id', $semesterId)
                            ->whereIn('registration_status', ['registered', 'confirmed'])
                            ->exists();

                        if ($existingRegistration) {
                            continue;
                        }

                        // Check capacity
                        if ($courseOffering->current_enrollment >= $courseOffering->max_capacity) {
                            continue;
                        }

                        // Create registration
                        CourseRegistration::create([
                            'student_id' => $student->id,
                            'course_offering_id' => $courseOffering->id,
                            'semester_id' => $semesterId,
                            'registration_status' => 'confirmed',
                            'registration_date' => now(),
                            'registration_method' => 'admin_override',
                            'credit_hours' => $curriculumUnit->unit->credit_points ?? 3,
                            'notes' => "Automated enrollment for new student",
                        ]);

                        // Update course offering enrollment count
                        $courseOffering->increment('current_enrollment');

                        // Update status if at capacity
                        if ($courseOffering->current_enrollment >= $courseOffering->max_capacity) {
                            $courseOffering->update(['enrollment_status' => 'closed']);
                        }

                        $result['created']++;
                    } catch (Exception $e) {
                        $result['errors'][] = "Failed to register student {$student->student_id} for {$curriculumUnit->unit->code}: {$e->getMessage()}";
                        Log::error('Course registration failed', [
                            'student_id' => $student->id,
                            'course_offering_id' => $courseOffering->id ?? 'unknown',
                            'unit_code' => $curriculumUnit->unit->code,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (Exception $e) {
                $result['errors'][] = "Failed to process enrollment for student ID {$enrollment->student_id}: {$e->getMessage()}";
                $result['skipped']++;
                Log::error('Student enrollment processing failed', [
                    'student_id' => $enrollment->student_id,
                    'enrollment_id' => $enrollment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Generate summary of the automated enrollment process
     */
    private function generateSummary(array $results, int $totalStudents): array
    {
        return [
            'total_students_processed' => $totalStudents,
            'enrollments_created' => $results['enrollments']['created'],
            'enrollments_skipped' => $results['enrollments']['skipped'],
            'course_offerings_created' => $results['course_offerings']['created'],
            'course_offerings_existing' => $results['course_offerings']['existing'],
            'registrations_created' => $results['course_registrations']['created'],
            'registrations_skipped' => $results['course_registrations']['skipped'],
            'total_errors' => count($results['enrollments']['errors']) +
                count($results['course_offerings']['errors']) +
                count($results['course_registrations']['errors']),
            'success_rate' => $totalStudents > 0 ?
                round(($results['enrollments']['created'] / $totalStudents) * 100, 2) : 0,
        ];
    }
}
