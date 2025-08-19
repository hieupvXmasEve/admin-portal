<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentApplicationRequest;
use App\Http\Requests\UpdateStudentApplicationRequest;
use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Specialization;
use App\Models\StudentApplication;
use App\Services\StudentApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class StudentApplicationController extends Controller
{
    public function __construct(
        private StudentApplicationService $studentApplicationService
    )
    {
    }

    /**
     * Display a listing of student applications
     */
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'converted' => $request->get('converted'),
            'campus_code' => $request->get('campus'),
            'per_page' => $request->get('per_page', 15),
            'sort' => $request->get('sort', 'created_at'),
            'direction' => $request->get('direction', 'desc'),
        ];

        $query = StudentApplication::query()
            ->with(['student' => function ($query) {
                $query->select('id', 'student_id', 'full_name');
            }]);

        // Apply search filter
        if ($filters['search']) {
            $query->where(function ($q) use ($filters) {
                $q->where('full_name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('national_id', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Apply status filter
        if ($filters['status']) {
            $query->where('status', $filters['status']);
        }

        // Apply converted filter
        if ($filters['converted'] !== null && $filters['converted'] !== '') {
            if ($filters['converted'] === 'yes') {
                $query->whereNotNull('student_id');
            } elseif ($filters['converted'] === 'no') {
                $query->whereNull('student_id');
            }
        }

        //        // Apply campus filter
        if ($filters['campus_code'] !== null && $filters['campus_code'] !== 'all') {
            $query->where('campus_code', $filters['campus_code']);
        }

        // Apply sorting
        $query->orderBy($filters['sort'], $filters['direction']);
        $applications = $query
//            ->where('campus_code', app('campus')->code)
            ->paginate($filters['per_page'])
            ->withQueryString();

        // Get filter options
        $campuses = Campus::select('code', 'name')->get();
        $statusOptions = [
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'reviewed', 'label' => 'Reviewed'],
            ['value' => 'approved', 'label' => 'Approved'],
            ['value' => 'rejected', 'label' => 'Rejected'],
        ];
        $conversionOptions = [
            ['value' => 'yes', 'label' => 'Converted to Student'],
            ['value' => 'no', 'label' => 'Not Converted'],
        ];

        return Inertia::render('student-applications/index', [
            'applications' => $applications,
            'filters' => $filters,
            'campuses' => $campuses,
            'statusOptions' => $statusOptions,
            'conversionOptions' => $conversionOptions,
        ]);
    }

    /**
     * Show the form for creating a new student application
     */
    public function create()
    {
        $campuses = Campus::select('id', 'code', 'name')->get();
        $programs = Program::select('id', 'name', 'code')->get();

        return Inertia::render('student-applications/create', [
            'campuses' => $campuses,
            'programs' => $programs,
        ]);
    }

    /**
     * Store a newly created student application
     */
    public function store(StoreStudentApplicationRequest $request)
    {
        $application = StudentApplication::create($request->validated());

        return redirect()
            ->route('student-applications.show', $application)
            ->with('success', 'Student application created successfully.');
    }

    /**
     * Display the specified student application
     */
    public function show(StudentApplication $studentApplication)
    {
        $studentApplication->load(['student' => function ($query) {
            $query->select('id', 'student_id', 'full_name', 'email', 'status');
        }]);

        return Inertia::render('student-applications/show', [
            'application' => $studentApplication,
        ]);
    }

    /**
     * Show the form for editing the specified student application
     */
    public function edit(StudentApplication $studentApplication)
    {
        $campuses = Campus::select('id', 'code', 'name')->get();
        $programs = Program::select('id', 'name', 'code')->get();

        return Inertia::render('student-applications/edit', [
            'application' => $studentApplication,
            'campuses' => $campuses,
            'programs' => $programs,
        ]);
    }

    /**
     * Update the specified student application
     */
    public function update(UpdateStudentApplicationRequest $request, StudentApplication $studentApplication)
    {
        $studentApplication->update($request->validated());

        return redirect()
            ->route('student-applications.show', $studentApplication)
            ->with('success', 'Student application updated successfully.');
    }

    /**
     * Remove the specified student application
     */
    public function destroy(StudentApplication $studentApplication)
    {
        // Only allow deletion if not converted to student
        if ($studentApplication->isConverted()) {
            return redirect()
                ->route('student-applications.index')
                ->with('error', 'Cannot delete application that has been converted to a student.');
        }

        $studentApplication->delete();

        return redirect()
            ->route('student-applications.index')
            ->with('success', 'Student application deleted successfully.');
    }

    /**
     * Update the status of a student application
     */
    public function updateStatus(Request $request, StudentApplication $studentApplication)
    {
        $request->validate([
            'status' => 'required|in:pending,reviewed,approved,rejected',
        ]);

        $studentApplication->update([
            'status' => $request->status,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Application status updated successfully.');
    }

    /**
     * Convert a single student application to a student
     */
    public function convert(Request $request, StudentApplication $studentApplication)
    {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'curriculum_version_id' => 'required|exists:curriculum_versions,id',
            'specialization_id' => 'nullable|exists:specializations,id',
            'admission_date' => 'required|date',
            'expected_graduation_date' => 'nullable|date|after:admission_date',
        ]);

        $result = $this->studentApplicationService->convertSingleApplication(
            $studentApplication->id,
            $request->only([
                'program_id',
                'curriculum_version_id',
                'specialization_id',
                'admission_date',
                'expected_graduation_date',
            ])
        );

        if ($result['success']) {
            return redirect()
                ->route('student-applications.show', $studentApplication)
                ->with('success', 'Application successfully converted to student.');
        }

        return redirect()
            ->back()
            ->withErrors($result['errors'] ?? ['error' => $result['error']])
            ->with('error', 'Failed to convert application.');
    }

    /**
     * Convert multiple student applications to students
     */
    public function batchConvert(Request $request)
    {
        $request->validate([
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'exists:student_applications,id',
            'admission_date' => 'required|date',
        ]);

        $result = $this->studentApplicationService->convertBatchApplications(
            $request->application_ids,
            $request->only([
                'admission_date',
            ])
        );

        $message = "Batch conversion completed: {$result['success_count']} successful, {$result['error_count']} failed.";

        if ($result['error_count'] > 0) {
            return redirect()
                ->back()
                ->with('warning', $message)
                ->with('batch_errors', $result['failed']);
        }

        return redirect()
            ->route('student-applications.index')
            ->with('success', $message);
    }

    /**
     * Get conversion options for forms
     */
    public function getConversionOptions(Request $request)
    {
        $data = [];

        // Get programs
        $data['programs'] = Program::select('id', 'name', 'code')->get();

        // Get curriculum versions for selected program
        if ($request->program_id) {
            $data['curriculumVersions'] = CurriculumVersion::where('program_id', $request->program_id)
                ->select('id', 'version_name', 'effective_date')
                ->get();
        }

        // Get specializations for selected program
        if ($request->program_id) {
            $data['specializations'] = Specialization::where('program_id', $request->program_id)
                ->select('id', 'name')
                ->get();
        }

        return response()->json($data);
    }

    /**
     * Update the status of multiple student applications
     */
    public function updateBulkStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'required|integer|exists:student_applications,id',
            'status' => 'required|in:pending,reviewed,approved,rejected',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $applicationIds = $request->application_ids;
            $newStatus = $request->status;

            $updated = DB::transaction(function () use ($applicationIds, $newStatus) {
                return StudentApplication::whereIn('id', $applicationIds)
                    ->update(['status' => $newStatus]);
            });

            return response()->json([
                'success' => true,
                'message' => "Successfully updated status for {$updated} application(s)",
                'data' => [
                    'updated_count' => $updated,
                    'new_status' => $newStatus,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update application statuses: ' . $e->getMessage(),
            ], 500);
        }
    }
}
