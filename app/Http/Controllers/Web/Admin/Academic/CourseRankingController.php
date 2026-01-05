<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin\Academic;

use App\Http\Controllers\Controller;
use App\Models\AcademicRecord;
use App\Models\Semester;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseRankingController extends Controller
{
    /**
     * Display the course ranking page.
     */
    public function index(Request $request): Response
    {
        $activeSemester = Semester::where('is_active', true)->first();

        $validated = $request->validate([
            'semester_id' => ['nullable', 'integer'],
        ]);

        $semesterId = isset($validated['semester_id'])
            ? (int) $validated['semester_id']
            : $activeSemester?->id;

        $rankingData = null;
        if ($semesterId) {
            $rankingData = $this->getCourseRankings($semesterId);
        }

        return Inertia::render('Academic/CourseRanking/Index', [
            'ranking' => $rankingData,
            'filters' => [
                'active' => [
                    'semester_id' => $semesterId,
                ],
                'options' => [
                    'semesters' => Semester::select('id', 'name')
                        ->orderBy('start_date', 'desc')
                        ->get(),
                ],
            ],
        ]);
    }

    /**
     * Get course rankings for a given semester.
     *
     * @param int $semesterId
     * @return array
     */
    private function getCourseRankings(int $semesterId): array
    {
        // Get all units that have academic records in this semester
        $units = Unit::whereHas('academicRecords', function ($query) use ($semesterId) {
            $query->where('semester_id', $semesterId)
                ->whereNotNull('final_percentage')
                ->where('unit_type', '!=', 'egc');
        })
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $courseRankings = [];

        foreach ($units as $unit) {
            // Get top 10 students for this unit, ordered by final_percentage DESC, then attendance_percentage DESC
            $topStudents = AcademicRecord::where('unit_id', $unit->id)
                ->where('semester_id', $semesterId)
                ->whereNotNull('final_percentage')
                ->with(['student:id,student_id,full_name'])
                ->orderBy('final_percentage', 'desc')
                ->orderBy('attendance_percentage', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($record) {
                    return [
                        'student_id' => $record->student->student_id,
                        'full_name' => $record->student->full_name,
                        'final_percentage' => (float) $record->final_percentage,
                        'attendance_percentage' => $record->attendance_percentage !== null
                            ? (float) $record->attendance_percentage
                            : null,
                    ];
                });

            if ($topStudents->isNotEmpty()) {
                $courseRankings[] = [
                    'unit' => [
                        'id' => $unit->id,
                        'code' => $unit->code,
                        'name' => $unit->name,
                    ],
                    'students' => $topStudents->toArray(),
                ];
            }
        }

        return [
            'semester_id' => $semesterId,
            'courses' => $courseRankings,
        ];
    }
}
