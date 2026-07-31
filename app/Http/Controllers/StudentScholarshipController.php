<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignScholarshipRequest;
use App\Models\ScholarshipDefinition;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Services\ScholarshipService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentScholarshipController extends Controller
{
    public function __construct(
        private ScholarshipService $scholarshipService
    ) {}

    /**
     * Display a listing of student scholarship assignments.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'scholarship_code' => 'nullable|string|max:50',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = StudentScholarshipAward::query()
            ->with(['student', 'scholarshipDefinition']);

        // Apply search filter
        if (! empty($validated['search'])) {
            $query->whereHas('student', function ($q) use ($validated) {
                $q->where('full_name', 'like', "%{$validated['search']}%")
                    ->orWhere('student_id', 'like', "%{$validated['search']}%")
                    ->orWhere('email', 'like', "%{$validated['search']}%");
            });
        }

        // Apply scholarship filter
        if (! empty($validated['scholarship_code'])) {
            $query->where('scholarship_code', $validated['scholarship_code']);
        }

        $assignments = $query->orderBy('awarded_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        // Get all scholarships for filter dropdown
        $scholarships = ScholarshipDefinition::active()
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('StudentScholarships/Index', [
            'assignments' => $assignments,
            'scholarships' => $scholarships,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'scholarship_code' => $validated['scholarship_code'] ?? '',
                'per_page' => $validated['per_page'] ?? 20,
            ],
        ]);
    }

    /**
     * Show the form for assigning a scholarship to a student.
     */
    public function create(): Response
    {
        $scholarships = ScholarshipDefinition::active()
            ->orderBy('code')
            ->get();

        return Inertia::render('StudentScholarships/Assign', [
            'scholarships' => $scholarships,
        ]);
    }

    /**
     * Assign a scholarship to a student.
     */
    public function store(AssignScholarshipRequest $request)
    {
        try {
            $this->scholarshipService->assignScholarshipToStudent(
                $request->validated('student_id'),
                $request->validated('scholarship_code'),
                [
                    'notes' => $request->validated('notes'),
                    'awarded_at' => $request->validated('awarded_at') ?? now(),
                ]
            );

            return redirect()->route('student-scholarships.index')
                ->with('success', 'Scholarship assigned successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove a scholarship assignment from a student.
     */
    public function destroy(StudentScholarshipAward $studentScholarship)
    {
        try {
            // FK would restrict anyway — refuse with a readable message when an
            // active per-semester adjustment still references this award.
            $hasActiveAdjustment = ScholarshipSemesterAdjustment::query()
                ->where('student_scholarship_award_id', $studentScholarship->id)
                ->whereIn('status', ScholarshipSemesterAdjustment::ACTIVE_STATUSES)
                ->exists();

            if ($hasActiveAdjustment) {
                return back()->withErrors([
                    'error' => 'This award has an active per-semester adjustment. Reverse the adjustment before removing the scholarship.',
                ]);
            }

            $studentScholarship->delete();

            return back()
                ->with('success', 'Scholarship assignment removed successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
