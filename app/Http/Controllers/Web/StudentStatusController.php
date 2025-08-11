<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AcademicHold;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\StudentStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentStatusController extends Controller
{
    public function __construct(
        private StudentStatusService $studentStatusService
    ) {}

    /**
     * Display student status tracking overview
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $campusId = session()->get('current_campus_id');

        if (! $campusId) {
            return redirect()->route('select-campus.index')
                ->with('error', 'Please select a campus first');
        }

        $validated = $request->validate([
            'status' => 'nullable|string|in:active,inactive,graduated,suspended,withdrawn',
            'search' => 'nullable|string|max:255',
        ]);

        $statistics = $this->studentStatusService->getStatusStatistics(['campus_id' => $campusId]);

        // Get students by status if filtered
        $students = null;
        if (! empty($validated['status'])) {
            $students = $this->studentStatusService->getStudentsByStatus(
                $validated['status'],
                array_merge($validated, ['campus_id' => $campusId])
            );
        }

        return Inertia::render('students/Status/Index', [
            'statistics' => $statistics,
            'students' => $students,
            'filters' => $validated,
        ]);
    }

    /**
     * Display student enrollments overview
     */
    public function enrollmentsIndex(Request $request): Response|RedirectResponse
    {
        $campusId = session()->get('current_campus_id');

        if (! $campusId) {
            return redirect()->route('select-campus.index')
                ->with('error', 'Please select a campus first');
        }

        $validated = $request->validate([
            'semester_id' => 'nullable|exists:semesters,id',
            'search' => 'nullable|string|max:255',
        ]);

        // Get enrollments for current campus
        $enrollmentsQuery = Enrollment::with(['student', 'semester'])
            ->whereHas('student', function ($q) use ($campusId) {
                $q->where('campus_id', $campusId);
            });

        if (! empty($validated['semester_id'])) {
            $enrollmentsQuery->where('semester_id', $validated['semester_id']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $enrollmentsQuery->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        $enrollments = $enrollmentsQuery->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('students/enrollments/Index', [
            'enrollments' => $enrollments,
            'filters' => $validated,
        ]);
    }

    /**
     * Display academic holds overview
     */
    public function holdsIndex(Request $request): Response|RedirectResponse
    {
        $campusId = session()->get('current_campus_id');

        if (! $campusId) {
            return redirect()->route('select-campus.index')
                ->with('error', 'Please select a campus first');
        }

        $validated = $request->validate([
            'status' => 'nullable|string|in:active,resolved',
            'search' => 'nullable|string|max:255',
        ]);

        // Get academic holds for current campus
        $holdsQuery = AcademicHold::with(['student'])
            ->whereHas('student', function ($q) use ($campusId) {
                $q->where('campus_id', $campusId);
            });

        if (! empty($validated['status'])) {
            $holdsQuery->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $holdsQuery->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        $holds = $holdsQuery->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('students/Holds/Index', [
            'holds' => $holds,
            'filters' => $validated,
        ]);
    }
}
