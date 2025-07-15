<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\CourseRegistration;
use App\Services\CourseRetakeService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class CourseRetakeController extends Controller
{
    public function __construct(
        private CourseRetakeService $courseRetakeService
    ) {}

    /**
     * Display global course retakes overview (for menu access)
     */
    public function globalIndex(Request $request): Response
    {
        $campusId = session()->get('current_campus_id');

        if (!$campusId) {
            return redirect()->route('select-campus.index')
                ->with('error', 'Please select a campus first');
        }

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'unit_id' => 'nullable|exists:units,id',
            'status' => 'nullable|string',
        ]);

        // Get retake registrations from the current campus
        $retakesQuery = CourseRegistration::with(['student', 'courseOffering.unit', 'courseOffering.semester'])
            ->where('is_retake', true)
            ->whereHas('student', function ($q) use ($campusId) {
                $q->where('campus_id', $campusId);
            });

        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $retakesQuery->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        if (!empty($validated['unit_id'])) {
            $retakesQuery->whereHas('courseOffering', function ($q) use ($validated) {
                $q->where('unit_id', $validated['unit_id']);
            });
        }

        $retakes = $retakesQuery->orderBy('registration_date', 'desc')->paginate(15);

        return Inertia::render('students/Retakes/GlobalIndex', [
            'retakes' => $retakes,
            'filters' => $validated,
        ]);
    }

    /**
     * Display retake history for a student
     */
    public function index(Student $student, Request $request): Response
    {
        $this->authorize('view', $student);

        $retakeHistory = $this->courseRetakeService->getRetakeHistory($student);

        return Inertia::render('students/Retakes/Index', [
            'student' => $student->load(['campus', 'program', 'specialization']),
            'retakes' => $retakeHistory,
        ]);
    }
}
