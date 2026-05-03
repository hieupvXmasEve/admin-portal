<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentStatusController extends Controller
{
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
}
