<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\AcademicStanding;
use App\Models\Semester;
use App\Services\AcademicStandingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicStandingController extends Controller
{
    public function __construct(
        private AcademicStandingService $academicStandingService
    ) {}

    /**
     * Display global academic standings overview
     */
    public function globalIndex(Request $request): Response
    {
        $campusId = session()->get('current_campus_id');

        if (!$campusId) {
            return redirect()->route('select-campus.index')
                ->with('error', 'Please select a campus first');
        }

        $validated = $request->validate([
            'standing' => 'nullable|string|in:good,probation,suspension,honors',
            'semester_id' => 'nullable|exists:semesters,id',
            'search' => 'nullable|string|max:255',
        ]);

        // Get academic standings for current campus
        $standingsQuery = AcademicStanding::with(['student', 'semester'])
            ->whereHas('student', function ($q) use ($campusId) {
                $q->where('campus_id', $campusId);
            })
            ->where('is_active', true);

        if (!empty($validated['standing'])) {
            $standingsQuery->where('standing', $validated['standing']);
        }

        if (!empty($validated['semester_id'])) {
            $standingsQuery->where('semester_id', $validated['semester_id']);
        }

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $standingsQuery->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        $standings = $standingsQuery->orderBy('effective_date', 'desc')->paginate(15);
        $semesters = Semester::orderBy('start_date', 'desc')->get();

        // Calculate statistics
        $totalStandings = AcademicStanding::whereHas('student', function ($q) use ($campusId) {
            $q->where('campus_id', $campusId);
        })->where('is_active', true);

        $statistics = [
            'total' => $totalStandings->count(),
            'good' => (clone $totalStandings)->where('standing', 'good')->count(),
            'probation' => (clone $totalStandings)->where('standing', 'probation')->count(),
            'suspension' => (clone $totalStandings)->where('standing', 'suspension')->count(),
            'honors' => (clone $totalStandings)->where('standing', 'honors')->count(),
        ];

        return Inertia::render('students/AcademicStandings/Index', [
            'standings' => $standings,
            'semesters' => $semesters,
            'statistics' => $statistics,
            'filters' => $validated,
        ]);
    }

    /**
     * Display academic standings for a specific student
     */
    public function index(Student $student, Request $request): Response
    {
        $this->authorize('view', $student);

        $standingHistory = $this->academicStandingService->getStandingHistory($student);

        return Inertia::render('students/AcademicStandings/StudentIndex', [
            'student' => $student->load(['campus', 'program', 'specialization']),
            'standings' => $standingHistory,
        ]);
    }
}
