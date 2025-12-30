<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin\Academic;

use App\Actions\Academic\GetAcademicReportAction;
use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicReportController extends Controller
{
    /**
     * Display the academic report page.
     */
    public function index(Request $request, GetAcademicReportAction $action): Response
    {
        $activeSemester = Semester::where('is_active', true)->first();
        $currentCampusId = session('current_campus_id');

        $validated = $request->validate([
            'campus_id' => ['nullable', 'integer'],
            'semester_id' => ['nullable', 'integer'],
            'program_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
            'keyword' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Set defaults if not provided in request
        $filters = array_merge([
            'semester_id' => $activeSemester?->id,
            'campus_id' => $currentCampusId,
            'per_page' => 15,
        ], array_filter($validated));

        $report = null;
        if (!empty($filters['semester_id'])) {
            $report = $action->execute($filters);
        }

        return Inertia::render('Academic/Report/Index', [
            'report' => $report,
            'filters' => [
                'active' => [
                    'campus_id' => isset($filters['campus_id']) ? (int) $filters['campus_id'] : null,
                    'semester_id' => isset($filters['semester_id']) ? (int) $filters['semester_id'] : null,
                    'program_id' => isset($filters['program_id']) ? (int) $filters['program_id'] : null,
                    'status' => $filters['status'] ?? 'all',
                    'keyword' => $filters['keyword'] ?? '',
                    'per_page' => (int) ($filters['per_page'] ?? 15),
                ],
                'options' => [
                    'campuses' => Campus::select('id', 'name')->get(),
                    'semesters' => Semester::select('id', 'name')->orderBy('start_date', 'desc')->get(),
                    'programs' => Program::select('id', 'name')->get(),
                    'statuses' => [
                        ['value' => 'active', 'label' => 'Active'],
                        ['value' => 'inactive', 'label' => 'Inactive'],
                        ['value' => 'graduated', 'label' => 'Graduated'],
                        ['value' => 'suspended', 'label' => 'Suspended'],
                        ['value' => 'intake_course', 'label' => 'In Course'],
                        ['value' => 'intake_pre_uni_gc', 'label' => 'In GC'],
                        ['value' => 'dropout', 'label' => 'Dropout'],
                    ],
                ]
            ]
        ]);
    }
}
