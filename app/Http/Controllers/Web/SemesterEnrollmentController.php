<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SemesterEnrollmentController extends Controller
{
    /**
     * Show the semester enrollment management page
     */
    public function show(Semester $semester): Response
    {
        // Get current campus ID from session
        $currentCampusId = session()->get('current_campus_id');

        // Load semester data with campus-filtered enrollments
        $semester->load([
            'enrollments' => function ($query) use ($currentCampusId) {
                if ($currentCampusId) {
                    $query->whereHas('student', function ($q) use ($currentCampusId) {
                        $q->where('campus_id', $currentCampusId);
                    });
                }
            },
            'enrollments.student.curriculumVersion',
            'courseOfferings.unit',
        ]);

        // Campus-filtered enrollment statistics
        $enrollmentQuery = $semester->enrollments();
        if ($currentCampusId) {
            $enrollmentQuery->whereHas('student', function ($query) use ($currentCampusId) {
                $query->where('campus_id', $currentCampusId);
            });
        }

        $enrollmentStats = [
            'total_enrolled' => $enrollmentQuery->count(),
            'by_status' => (clone $enrollmentQuery)
                ->groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->pluck('count', 'status')
                ->toArray(),
            'by_semester_number' => (clone $enrollmentQuery)
                ->groupBy('semester_number')
                ->selectRaw('semester_number, count(*) as count')
                ->orderBy('semester_number')
                ->pluck('count', 'semester_number')
                ->toArray(),
        ];

        // Get campus-specific student statistics
        $campusStats = [];
        if ($currentCampusId) {
            $campus = Campus::find($currentCampusId);

            // Get total eligible students for this campus
            $totalEligibleStudents = Student::query()
                ->whereIn('status', ['intake_pre_uni_gc', 'intake_course'])
                ->where('academic_status', 'active')
                ->where('campus_id', $currentCampusId)
                ->whereNotNull('curriculum_version_id')
                ->count();

            // Get students already enrolled for this semester
            $enrolledStudents = Student::query()
                ->whereIn('status', ['intake_pre_uni_gc', 'intake_course'])
                ->where('academic_status', 'active')
                ->where('campus_id', $currentCampusId)
                ->whereNotNull('curriculum_version_id')
                ->whereHas('enrollments', function ($query) use ($semester) {
                    $query->where('semester_id', $semester->id);
                })
                ->count();

            // Get students not yet enrolled for this semester
            $notEnrolledStudents = Student::query()
                ->whereIn('status', ['intake_pre_uni_gc', 'intake_course'])
                ->where('academic_status', 'active')
                ->where('campus_id', $currentCampusId)
                ->whereNotNull('curriculum_version_id')
                ->whereDoesntHave('enrollments', function ($query) use ($semester) {
                    $query->where('semester_id', $semester->id);
                })
                ->whereDoesntHave('academicHolds', function ($query) {
                    $query->where('hold_category', 'registration')->where('status', 'active');
                })
                ->count();

            $campusStats = [
                'campus_name' => $campus->name ?? 'Unknown Campus',
                'campus_code' => $campus->code ?? 'N/A',
                'total_eligible_students' => $totalEligibleStudents,
                'enrolled_students' => $enrolledStudents,
                'not_enrolled_students' => $notEnrolledStudents,
                'enrollment_rate' => $totalEligibleStudents > 0
                    ? round(($enrolledStudents / $totalEligibleStudents) * 100, 1)
                    : 0,
            ];
        }

        return Inertia::render('Semesters/Enrollment', [
            'semester' => $semester,
            'enrollmentStats' => $enrollmentStats,
            'campusStats' => $campusStats,
        ]);
    }

    /**
     * Generate enrollments for students
     */
    public function generateEnrollments(Semester $semester): JsonResponse
    {
        // Get current campus ID from session
        $currentCampusId = session()->get('current_campus_id');

        if (! $currentCampusId) {
            return response()->json([
                'success' => false,
                'message' => 'No campus selected. Please select a campus first.',
            ], 400);
        }

        // Get active students with valid curriculum_version_id for current campus
        $eligibleStudents = Student::query()
            ->whereIn('status', ['intake_pre_uni_gc', 'intake_course'])
            ->where('academic_status', 'active')
            ->where('campus_id', $currentCampusId)
            ->whereNotNull('curriculum_version_id')
            ->whereDoesntHave('enrollments', fn ($q) => $q->where('semester_id', $semester->id))
            ->whereDoesntHave('academicHolds', fn ($q) => $q->where('hold_category', 'registration')->where('status', 'active'))
            ->get();

        if ($eligibleStudents->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No eligible students found for enrollment in the selected campus',
            ], 200);
        }

        // Prefetch each student's latest semester_number in one query instead
        // of one query per student inside the loop below.
        $latestSemesterNumbers = Enrollment::whereIn('student_id', $eligibleStudents->pluck('id'))
            ->selectRaw('student_id, MAX(semester_number) as max_semester_number')
            ->groupBy('student_id')
            ->pluck('max_semester_number', 'student_id');

        try {
            DB::beginTransaction();

            $enrollmentsCreated = 0;
            $errors = [];

            foreach ($eligibleStudents as $student) {
                try {
                    $semesterNumber = ($latestSemesterNumbers[$student->id] ?? 0) + 1;

                    // Validate semester number doesn't exceed reasonable limits
                    if ($semesterNumber > 8) {
                        $errors[] = "Student {$student->student_id} has exceeded maximum semester limit";

                        continue;
                    }

                    Enrollment::create([
                        'student_id' => $student->id,
                        'semester_id' => $semester->id,
                        'curriculum_version_id' => $student->curriculum_version_id,
                        'semester_number' => $semesterNumber,
                        'status' => 'in_progress',
                    ]);

                    $enrollmentsCreated++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to enroll student {$student->student_id}";
                    Log::error('Enrollment creation failed', [
                        'student_id' => $student->id,
                        'semester_id' => $semester->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            // Get updated campus statistics
            $campus = Campus::find($currentCampusId);
            $totalEligibleStudents = Student::query()
                ->whereIn('status', ['intake_pre_uni_gc', 'intake_course'])
                ->where('academic_status', 'active')
                ->where('campus_id', $currentCampusId)
                ->whereNotNull('curriculum_version_id')
                ->count();
            $campusName = $campus->name ?? 'campus';

            return response()->json([
                'success' => true,
                'message' => "Successfully created {$enrollmentsCreated} enrollments for {$campusName}",
                'enrollments_created' => $enrollmentsCreated,
                'total_eligible_students' => $totalEligibleStudents,
                'campus_name' => $campus->name ?? 'Unknown Campus',
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Generate enrollments failed', [
                'semester_id' => $semester->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate enrollments. Please try again or contact support.',
            ], 500);
        }
    }
}
