<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\StudentRegistry\SemesterEnrollmentEligibilityReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SemesterEnrollmentController extends Controller
{
    public function __construct(
        private readonly AcademicPeriodReader $periods,
        private readonly CampusReferenceReader $campuses,
        private readonly SemesterEnrollmentEligibilityReader $eligibility,
    ) {}

    /**
     * Show the semester enrollment management page
     */
    public function show(string $semester): Response
    {
        $semesterId = (int) $semester;
        $period = $this->periods->find($semesterId);
        abort_if($period === null, 404);

        $currentCampusId = session()->get('current_campus_id');

        $enrollmentQuery = Enrollment::query()->where('semester_id', $semesterId);
        if ($currentCampusId) {
            $enrollmentQuery->whereHas('student', fn ($q) => $q->where('campus_id', $currentCampusId));
        }

        $enrollmentStats = [
            'total_enrolled' => (clone $enrollmentQuery)->count(),
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

        $campusStats = [];
        if ($currentCampusId) {
            $campus = $this->campuses->find((int) $currentCampusId);
            $counts = $this->eligibility->counts((int) $currentCampusId, $semesterId);

            $campusStats = [
                'campus_name' => $campus?->name ?? 'Unknown Campus',
                'campus_code' => $campus?->code ?? 'N/A',
                'total_eligible_students' => $counts->totalEligible,
                'enrolled_students' => $counts->enrolled,
                'not_enrolled_students' => $counts->notEnrolled,
                'enrollment_rate' => $counts->totalEligible > 0
                    ? round(($counts->enrolled / $counts->totalEligible) * 100, 1)
                    : 0,
            ];
        }

        return Inertia::render('Semesters/Enrollment', [
            'semester' => [
                'id' => $period->id,
                'name' => $period->name,
                'course_offerings_count' => $this->periods->courseOfferingCount($semesterId),
            ],
            'enrollmentStats' => $enrollmentStats,
            'campusStats' => $campusStats,
        ]);
    }

    /**
     * Generate enrollments for students
     */
    public function generateEnrollments(string $semester): JsonResponse
    {
        $semesterId = (int) $semester;
        abort_if($this->periods->find($semesterId) === null, 404);

        $currentCampusId = session()->get('current_campus_id');

        if (! $currentCampusId) {
            return response()->json([
                'success' => false,
                'message' => 'No campus selected. Please select a campus first.',
            ], 400);
        }

        $currentCampusId = (int) $currentCampusId;

        $candidates = $this->eligibility->eligibleForSemester($currentCampusId, $semesterId);

        if (empty($candidates)) {
            return response()->json([
                'success' => false,
                'message' => 'No eligible students found for enrollment in the selected campus',
            ], 200);
        }

        // Prefetch each candidate's latest semester_number in one query instead
        // of one query per student inside the loop below.
        $latestSemesterNumbers = Enrollment::whereIn('student_id', array_map(fn ($c) => $c->id, $candidates))
            ->selectRaw('student_id, MAX(semester_number) as max_semester_number')
            ->groupBy('student_id')
            ->pluck('max_semester_number', 'student_id');

        try {
            DB::beginTransaction();

            $enrollmentsCreated = 0;
            $errors = [];

            foreach ($candidates as $candidate) {
                try {
                    $semesterNumber = ($latestSemesterNumbers[$candidate->id] ?? 0) + 1;

                    // Validate semester number doesn't exceed reasonable limits
                    if ($semesterNumber > 8) {
                        $errors[] = "Student {$candidate->studentCode} has exceeded maximum semester limit";

                        continue;
                    }

                    Enrollment::create([
                        'student_id' => $candidate->id,
                        'semester_id' => $semesterId,
                        'curriculum_version_id' => $candidate->curriculumVersionId,
                        'semester_number' => $semesterNumber,
                        'status' => 'in_progress',
                    ]);

                    $enrollmentsCreated++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to enroll student {$candidate->studentCode}";
                    Log::error('Enrollment creation failed', [
                        'student_id' => $candidate->id,
                        'semester_id' => $semesterId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();

            $campus = $this->campuses->find($currentCampusId);
            $counts = $this->eligibility->counts($currentCampusId, $semesterId);
            $campusName = $campus?->name ?? 'campus';

            return response()->json([
                'success' => true,
                'message' => "Successfully created {$enrollmentsCreated} enrollments for {$campusName}",
                'enrollments_created' => $enrollmentsCreated,
                'total_eligible_students' => $counts->totalEligible,
                'campus_name' => $campus?->name ?? 'Unknown Campus',
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Generate enrollments failed', [
                'semester_id' => $semesterId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate enrollments. Please try again or contact support.',
            ], 500);
        }
    }
}
