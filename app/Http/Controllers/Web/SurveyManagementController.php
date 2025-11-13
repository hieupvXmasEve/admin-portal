<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\FormSurvey;
use App\Models\StudentFormSurvey;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SurveyManagementController extends Controller
{
    /**
     * Display a listing of course surveys
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $campusId = app('campus')->id;

        $query = FormSurvey::with([
            'form',
            'formVersion',
            'courseOffering.unit',
            'courseOffering.semester',
            'courseOffering.campus',
        ])
            ->whereHas('courseOffering', function ($q) use ($campusId) {
                $q->where('campus_id', $campusId);
            });

        // Apply search filter
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('courseOffering', function ($q) use ($search) {
                    $q->whereHas('unit', function ($q) use ($search) {
                        $q->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
                })
                    ->orWhereHas('form', function ($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        // Apply semester filter
        if (!empty($validated['semester_id'])) {
            $query->whereHas('courseOffering', function ($q) use ($validated) {
                $q->where('semester_id', $validated['semester_id']);
            });
        }

        // Get statistics for each survey
        $surveys = $query->withCount([
            'studentSurveys as total_students',
            'studentSurveys as completed_count' => function ($q) {
                $q->where('status', 'completed');
            },
            'studentSurveys as pending_count' => function ($q) {
                $q->where('status', '!=', 'completed');
            },
        ])
            ->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        // Get semesters for filter dropdown
        $semesters = \App\Models\Semester::orderBy('start_date', 'desc')
            ->limit(10)
            ->get(['id', 'code', 'name', 'start_date', 'end_date']);

        return Inertia::render('Surveys/Index', [
            'surveys' => $surveys,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'semester_id' => $validated['semester_id'] ?? null,
                'per_page' => $validated['per_page'] ?? 20,
            ],
            'semesters' => $semesters,
        ]);
    }

    /**
     * Display the specified survey with student statistics
     */
    public function show(FormSurvey $survey, Request $request): Response
    {
        // Ensure survey belongs to current campus
        if ($survey->courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => 'nullable|string|in:all,completed,pending',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        // Load survey with relationships
        $survey->load([
            'form',
            'formVersion.sections.questions.options',
            'formVersion.questions.options',
            'courseOffering.unit',
            'courseOffering.semester',
            'courseOffering.campus',
        ]);

        // Get student surveys with filters
        $query = StudentFormSurvey::with([
            'student',
            'response' => function ($q) {
                $q->with([
                    'answers.question',
                    'answers.selectedOptions',
                ]);
            },
        ])
            ->where('form_survey_id', $survey->id);

        // Apply status filter
        if (!empty($validated['status']) && $validated['status'] !== 'all') {
            if ($validated['status'] === 'completed') {
                $query->where('status', 'completed');
            } else {
                $query->where('status', '!=', 'completed');
            }
        }

        // Apply search filter
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $studentSurveys = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        // Get statistics
        $statistics = [
            'total_students' => StudentFormSurvey::where('form_survey_id', $survey->id)->count(),
            'completed' => StudentFormSurvey::where('form_survey_id', $survey->id)
                ->where('status', 'completed')
                ->count(),
            'pending' => StudentFormSurvey::where('form_survey_id', $survey->id)
                ->where('status', '!=', 'completed')
                ->count(),
            'completion_rate' => 0,
        ];

        if ($statistics['total_students'] > 0) {
            $statistics['completion_rate'] = round(
                ($statistics['completed'] / $statistics['total_students']) * 100,
                2
            );
        }

        return Inertia::render('Surveys/Show', [
            'survey' => $survey,
            'studentSurveys' => $studentSurveys,
            'statistics' => $statistics,
            'filters' => [
                'status' => $validated['status'] ?? 'all',
                'search' => $validated['search'] ?? null,
                'per_page' => $validated['per_page'] ?? 20,
            ],
        ]);
    }
}
