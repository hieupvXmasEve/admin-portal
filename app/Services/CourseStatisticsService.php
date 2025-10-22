<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CourseOffering;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CourseStatisticsService
{
    public function getStatistics(array $filters): LengthAwarePaginator
    {
        $campusId = session('current_campus_id');
        $perPage = $filters['per_page'] ?? 15;
        $search = $filters['search'] ?? null;
        $semesterId = $filters['semester_id'] ?? null;
        $sortBy = $filters['sort'] ?? 'course_code';
        $direction = $filters['direction'] ?? 'asc';

        $query = CourseOffering::query()
            ->with([
                'semester:id,name,code',
                'unit:id,code,name,credit_points',
                'lecture:id,first_name,last_name',
                'classSessions:id,course_offering_id',
                'academicRecords:id,course_offering_id,student_id,total_absences',
            ])
            ->where('course_offerings.campus_id', $campusId)
            ->where('course_offerings.is_active', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('unit', function ($unitQuery) use ($search) {
                    $unitQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                })
                    ->orWhere('course_offerings.section_code', 'like', "%{$search}%");
            });
        }

        $query->leftJoin('units', 'course_offerings.unit_id', '=', 'units.id')
            ->leftJoin('semesters', 'course_offerings.semester_id', '=', 'semesters.id')
            ->leftJoin('academic_records', 'course_offerings.id', '=', 'academic_records.course_offering_id');

        if ($semesterId) {
            $query->where('course_offerings.semester_id', $semesterId);
        }

        $query
            ->select([
                'course_offerings.id',
                'course_offerings.semester_id',
                'course_offerings.unit_id',
                'course_offerings.lecture_id',
                'course_offerings.campus_id',
                'course_offerings.section_code',
                'course_offerings.max_capacity',
                'course_offerings.current_enrollment',
                'course_offerings.delivery_mode',
                DB::raw('COUNT(DISTINCT academic_records.student_id) as total_students'),
                DB::raw('SUM(CASE WHEN academic_records.meets_attendance_requirement = 0 THEN 1 ELSE 0 END) as students_absent_exceeded'),
                DB::raw('AVG(academic_records.attendance_percentage) as average_attendance'),
                DB::raw('AVG(academic_records.final_percentage) as average_grade'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "A+" THEN 1 END) as grade_a_plus'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "A" THEN 1 END) as grade_a'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "B+" THEN 1 END) as grade_b_plus'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "B" THEN 1 END) as grade_b'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "C+" THEN 1 END) as grade_c_plus'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "C" THEN 1 END) as grade_c'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "D+" THEN 1 END) as grade_d_plus'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "D" THEN 1 END) as grade_d'),
                DB::raw('COUNT(CASE WHEN academic_records.final_letter_grade = "F" THEN 1 END) as grade_f'),
                DB::raw('SUM(CASE WHEN academic_records.completion_status = "completed" AND academic_records.grade_points > 0 THEN 1 ELSE 0 END) as students_passed'),
            ])
            ->groupBy(
                'course_offerings.id',
                'course_offerings.semester_id',
                'course_offerings.unit_id',
                'course_offerings.lecture_id',
                'course_offerings.campus_id',
                'course_offerings.section_code',
                'course_offerings.max_capacity',
                'course_offerings.current_enrollment',
                'course_offerings.delivery_mode'
            );

        $sortColumn = match ($sortBy) {
            'course_name' => 'units.name',
            'semester' => 'semesters.name',
            'total_students' => 'total_students',
            'average_attendance' => 'average_attendance',
            'average_grade' => 'average_grade',
            default => 'units.code',
        };

        $query->orderBy($sortColumn, $direction);

        return $query->paginate($perPage)->withQueryString();
    }

    public function getCourseDetail(int $courseOfferingId): array
    {
        $courseOffering = CourseOffering::with([
            'semester',
            'unit',
            'lecture',
            'academicRecords' => function ($query) {
                $query->with('student:id,student_id,full_name,email');
            },
        ])->findOrFail($courseOfferingId);

        $statistics = [
            'course_offering' => $courseOffering,
            'total_students' => $courseOffering->academicRecords->count(),
            'students_absent_exceeded' => $courseOffering->academicRecords->where('meets_attendance_requirement', false)->count(),
            'average_attendance' => round($courseOffering->academicRecords->avg('attendance_percentage') ?? 0, 2),
            'average_grade' => round($courseOffering->academicRecords->avg('final_percentage') ?? 0, 2),
            'grade_distribution' => $this->calculateGradeDistribution($courseOffering->academicRecords),
            'pass_rate' => $this->calculatePassRate($courseOffering->academicRecords),
            'students' => $courseOffering->academicRecords,
        ];

        return $statistics;
    }

    private function calculateGradeDistribution($academicRecords): array
    {
        $distribution = [
            'A+' => 0, 'A' => 0, 'B+' => 0, 'B' => 0,
            'C+' => 0, 'C' => 0, 'D+' => 0, 'D' => 0, 'F' => 0,
        ];

        foreach ($academicRecords as $record) {
            if ($record->final_letter_grade && isset($distribution[$record->final_letter_grade])) {
                $distribution[$record->final_letter_grade]++;
            }
        }

        return $distribution;
    }

    private function calculatePassRate($academicRecords): float
    {
        $total = $academicRecords->count();
        if ($total === 0) {
            return 0.0;
        }

        $passed = $academicRecords->filter(function ($record) {
            return $record->completion_status === 'completed' && $record->grade_points > 0;
        })->count();

        return round(($passed / $total) * 100, 2);
    }

    public function getAttendanceGrid(int $courseOfferingId): array
    {
        $courseOffering = CourseOffering::with([
            'semester',
            'unit',
            'lecture',
            'campus',
            'classSessions' => function ($query) {
                $query->orderBy('session_date', 'asc')
                    ->orderBy('start_time', 'asc');
            },
            'classSessions.attendances.student',
        ])->findOrFail($courseOfferingId);

        $students = $courseOffering->courseRegistrations()
            ->with('student')
            ->get()
            ->pluck('student')
            ->sortBy('student_id');

        $sessions = $courseOffering->classSessions;
        $totalSessions = $sessions->count();
        $allowedAbsences = (int) ceil($totalSessions * 0.2); // 20% allowed absences

        $attendanceGrid = [];
        foreach ($students as $student) {
            $studentData = [
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'sessions' => [],
            ];

            $totalAbsences = 0;
            $totalPresent = 0;
            $totalLate = 0;

            foreach ($sessions as $session) {
                $attendance = $session->attendances->firstWhere('student_id', $student->id);
                $status = $attendance?->status ?? 'not_recorded';

                // Count attendance status
                if ($status === 'absent') {
                    $totalAbsences++;
                } elseif ($status === 'present') {
                    $totalPresent++;
                } elseif ($status === 'late') {
                    $totalLate++;
                }

                $studentData['sessions'][] = [
                    'session_id' => $session->id,
                    'session_number' => $session->sequence_number,
                    'session_date' => $session->session_date->format('Y-m-d'),
                    'status' => $status,
                    'check_in_time' => $attendance?->check_in_time?->format('H:i'),
                    'minutes_late' => $attendance?->minutes_late,
                ];
            }

            // Calculate attendance percentage
            $attendancePercentage = $totalSessions > 0
                ? round((($totalPresent + $totalLate) / $totalSessions) * 100, 2)
                : 0;

            // Determine if meets requirement based on absences vs allowed
            $meetsRequirement = $totalAbsences <= $allowedAbsences;

            $studentData['total_present'] = $totalPresent;
            $studentData['total_absences'] = $totalAbsences;
            $studentData['total_late'] = $totalLate;
            $studentData['attendance_percentage'] = $attendancePercentage;
            $studentData['meets_attendance_requirement'] = $meetsRequirement;
            $studentData['allowed_absences'] = $allowedAbsences;
            $studentData['absences_remaining'] = max(0, $allowedAbsences - $totalAbsences);

            $attendanceGrid[] = $studentData;
        }

        // Count students who exceeded allowed absences
        $studentsAbsentExceeded = collect($attendanceGrid)->filter(function ($student) {
            return ! $student['meets_attendance_requirement'];
        })->count();

        $statistics = [
            'course_code' => $courseOffering->unit->code,
            'course_name' => $courseOffering->unit->name,
            'section_code' => $courseOffering->section_code,
            'semester' => $courseOffering->semester->name,
            'instructor_name' => $courseOffering->lecture ? trim($courseOffering->lecture->first_name.' '.$courseOffering->lecture->last_name) : null,
            'total_students' => $students->count(),
            'total_sessions' => $totalSessions,
            'allowed_absences' => $allowedAbsences,
            'students_absent_exceeded' => $studentsAbsentExceeded,
        ];

        return [
            'course_offering' => $courseOffering,
            'statistics' => $statistics,
            'sessions' => $sessions->map(function ($session) {
                return [
                    'id' => $session->id,
                    'session_number' => $session->sequence_number,
                    'session_date' => $session->session_date->format('Y-m-d'),
                    'session_title' => $session->session_title,
                    'session_time_start' => $session->start_time?->format('H:i'),
                    'session_time_end' => $session->end_time?->format('H:i'),
                ];
            }),
            'attendance_grid' => $attendanceGrid,
        ];
    }

    public function getAssessmentScoresGrid(int $courseOfferingId): array
    {
        $courseOffering = CourseOffering::with([
            'semester',
            'unit',
            'lecture',
            'campus',
            'syllabusTemplate.assessmentComponents' => function ($query) {
                $query->orderBy('sort_order')->orderBy('id');
            },
            'syllabusTemplate.assessmentComponents.details' => function ($query) {
                $query->orderBy('id');
            },
            'courseRegistrations.student',
        ])->findOrFail($courseOfferingId);

        // Get all students enrolled in the course
        $students = $courseOffering->courseRegistrations()
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter()
            ->sortBy('student_id');

        // Get assessment components from syllabus template
        $assessmentComponents = $courseOffering->syllabusTemplate?->assessmentComponents ?? collect([]);

        // Build list of all assessment component details for columns
        $assessmentDetails = [];
        foreach ($assessmentComponents as $component) {
            // Ensure component has at least one detail (auto-create if missing)
            if ($component->details->isEmpty()) {
                $component->ensureHasDetails();
                $component->load('details'); // Reload details after creation
            }

            foreach ($component->details as $detail) {
                $assessmentDetails[] = [
                    'id' => $detail->id,
                    'component_id' => $component->id,
                    'component_name' => $component->name,
                    'component_type' => $component->type,
                    'component_weight' => $component->weight,
                    'detail_name' => $detail->name,
                    'detail_weight' => $detail->weight,
                    'max_points' => $detail->max_points,
                    'grading_type' => $detail->grading_type ?? 'points',
                    'submission_types' => $detail->submission_types ?? [],
                    'canvas_assignment_id' => $detail->canvas_assignment_id,
                ];
            }
        }

        // Get all scores for this course offering
        $allScores = DB::table('assessment_component_detail_scores')
            ->whereIn('student_id', $students->pluck('id'))
            ->where('course_offering_id', $courseOfferingId)
            ->whereNull('deleted_at')
            ->get()
            ->groupBy('student_id');

        // Get academic records for totals and grade status
        $academicRecords = DB::table('academic_records')
            ->whereIn('student_id', $students->pluck('id'))
            ->where('course_offering_id', $courseOfferingId)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('student_id');

        // Build scores grid - IMPORTANT: Every student must have scores for ALL assessment details
        $scoresGrid = [];
        foreach ($students as $student) {
            $studentScores = $allScores->get($student->id, collect([]));
            $scoresByDetailId = $studentScores->keyBy('assessment_component_detail_id');

            // CRITICAL: Create scores array for ALL assessment details (even if no score exists)
            $scores = [];
            foreach ($assessmentDetails as $detail) {
                $score = $scoresByDetailId->get($detail['id']);

                // Always add entry for this assessment detail, even if no score
                $scores[] = [
                    'component_detail_id' => $detail['id'],
                    'percentage_score' => $score?->percentage_score ?? null,
                    'letter_grade' => $score?->letter_grade ?? null,
                    'status' => $score?->status ?? 'not_submitted',
                    'score_status' => $score?->score_status ?? 'draft',
                    'graded_at' => $score?->graded_at ?? null,
                    'is_late' => $score?->is_late ?? false,
                    'score_excluded' => $score?->score_excluded ?? false,
                ];
            }

            // Calculate component totals for this student
            $componentTotals = [];
            foreach ($assessmentComponents as $component) {
                // Special handling for attendance component type
                if ($component->type === 'attendance') {
                    $academicRecord = $academicRecords->get($student->id);
                    $attendancePercentage = $academicRecord?->attendance_percentage ?? null;

                    $componentTotals[] = [
                        'component_id' => $component->id,
                        'percentage_score' => $attendancePercentage !== null ? round((float) $attendancePercentage, 2) : null,
                        'contribution_to_final' => $attendancePercentage !== null ? round(((float) $attendancePercentage * (float) $component->weight) / 100, 2) : null,
                        'out_of_weight' => $component->weight,
                        'is_attendance' => true,
                    ];

                    continue;
                }

                // Filter out details with max_points = 0 (they don't contribute to component total)
                $validDetails = $component->details->filter(function ($detail) {
                    return $detail->max_points === null || $detail->max_points > 0;
                });

                $detailIds = $validDetails->pluck('id')->toArray();

                // Get all scores for this component's details
                $componentScores = $studentScores->whereIn('assessment_component_detail_id', $detailIds);

                // Check if details have weights defined
                $detailsHaveWeights = $validDetails->filter(fn ($d) => $d->weight !== null && $d->weight > 0)->count() > 0;

                // Calculate weighted sum based on detail weights
                $totalWeightedScore = 0;
                $maxPossibleScore = 0;
                $hasAnyScore = false;
                $detailCount = $validDetails->count();

                if ($detailsHaveWeights) {
                    // Use actual weights from details
                    foreach ($validDetails as $detail) {
                        $score = $componentScores->firstWhere('assessment_component_detail_id', $detail->id);
                        $detailWeight = $detail->weight ?? 0;
                        $maxPossibleScore += $detailWeight;

                        if ($score && ! $score->score_excluded && $score->percentage_score !== null) {
                            $hasAnyScore = true;
                            $totalWeightedScore += ($score->percentage_score * $detailWeight) / 100;
                        }
                    }
                } else {
                    // No weights defined, treat all valid details equally
                    $equalWeight = $detailCount > 0 ? 100 / $detailCount : 0;
                    foreach ($validDetails as $detail) {
                        $score = $componentScores->firstWhere('assessment_component_detail_id', $detail->id);
                        $maxPossibleScore += $equalWeight;

                        if ($score && ! $score->score_excluded && $score->percentage_score !== null) {
                            $hasAnyScore = true;
                            $totalWeightedScore += ($score->percentage_score * $equalWeight) / 100;
                        }
                    }
                }

                // Calculate component percentage (out of 100%)
                $componentPercentage = null;
                if ($hasAnyScore && $maxPossibleScore > 0) {
                    $componentPercentage = ($totalWeightedScore / $maxPossibleScore) * 100;
                }

                // Calculate actual contribution to final grade (component percentage × component weight)
                $contributionToFinal = null;
                if ($componentPercentage !== null) {
                    $contributionToFinal = ($componentPercentage * $component->weight) / 100;
                }

                $componentTotals[] = [
                    'component_id' => $component->id,
                    'percentage_score' => $componentPercentage !== null ? round($componentPercentage, 2) : null,
                    'contribution_to_final' => $contributionToFinal !== null ? round($contributionToFinal, 2) : null,
                    'out_of_weight' => $component->weight,
                    'is_attendance' => false,
                ];
            }

            // Get totals from academic_records (not calculated)
            $academicRecord = $academicRecords->get($student->id);

            $scoresGrid[] = [
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'scores' => $scores, // This array MUST have same length as $assessmentDetails
                'component_totals' => $componentTotals, // NEW: Component totals for this student
                'total_percentage' => $academicRecord?->final_percentage ?? null,
                'total_letter_grade' => $academicRecord?->final_letter_grade ?? null,
                'grade_status' => $academicRecord?->grade_status ?? 'not_graded',
                'completion_status' => $academicRecord?->completion_status ?? 'in_progress',
            ];
        }

        // Calculate average from academic records
        $averageScore = $academicRecords->where('final_percentage', '!=', null)->avg('final_percentage');

        // Calculate statistics
        $statistics = [
            'course_code' => $courseOffering->unit->code,
            'course_name' => $courseOffering->unit->name,
            'section_code' => $courseOffering->section_code,
            'semester' => $courseOffering->semester->name,
            'instructor_name' => $courseOffering->lecture ? trim($courseOffering->lecture->first_name.' '.$courseOffering->lecture->last_name) : null,
            'total_students' => $students->count(),
            'total_components' => $assessmentComponents->count(),
            'total_details' => count($assessmentDetails),
            'average_score' => $averageScore ? round($averageScore, 2) : 0,
        ];

        return [
            'course_offering' => ['id' => $courseOffering->id],
            'statistics' => $statistics,
            'assessment_components' => $assessmentComponents->map(function ($component) {
                return [
                    'id' => $component->id,
                    'name' => $component->name,
                    'type' => $component->type,
                    'weight' => $component->weight,
                    'details' => $component->details->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'name' => $detail->name,
                            'weight' => $detail->weight,
                            'max_points' => $detail->max_points,
                        ];
                    })->toArray(),
                ];
            })->toArray(),
            'assessment_details' => $assessmentDetails,
            'scores_grid' => $scoresGrid,
        ];
    }
}
