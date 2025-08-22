<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportStudentApplicationPreviewRequest;
use App\Http\Requests\ImportStudentApplicationProcessRequest;
use App\Http\Requests\StoreStudentApplicationRequest;
use App\Http\Requests\UpdateStudentApplicationRequest;
use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Specialization;
use App\Models\StudentApplication;
use App\Services\StudentApplicationImportService;
use App\Services\StudentApplicationService;
use App\Exports\StudentApplicationExport;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class StudentApplicationController extends Controller
{
    public function __construct(
        private StudentApplicationService $studentApplicationService,
        private StudentApplicationImportService $importService
    ) {}

    /**
     * Display a listing of student applications
     */
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'converted' => $request->get('converted'),
            'campus_code' => $request->get('campus_code'),
            'per_page' => min((int) $request->get('per_page', 15), 200),
            'sort' => $request->get('sort', 'created_at'),
            'direction' => $request->get('direction', 'desc'),
            'overall_operator' => $request->get('overall_operator'),
            'overall_value' => $request->get('overall_value'),
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
                    ->orWhere('student_code', 'like', '%' . $filters['search'] . '%')
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

        // Apply overall score filter
        if ($filters['overall_operator'] && $filters['overall_value'] !== null) {
            $operator = match ($filters['overall_operator']) {
                'gt' => '>',
                'gte' => '>=',
                'lt' => '<',
                'lte' => '<=',
                'eq' => '=',
                default => '='
            };
            // $query->where('overall', $operator, $filters['overall_value']);
            $query->whereRaw('COALESCE(overall, 0) ' . $operator . ' ?', [
                $filters['overall_value']
            ]);
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
     * Now with automatic campus_id and curriculum_version_id resolution
     */
    public function batchConvert(Request $request)
    {
        $request->validate([
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'exists:student_applications,id',
            'admission_date' => 'required|date',
            'expected_graduation_date' => 'nullable|date|after:admission_date',
        ]);

        try {
            // The service will now automatically resolve campus_id, program_id, and curriculum_version_id
            // from the application's campus_code, intended_program, and intake fields
            $result = $this->studentApplicationService->convertBatchApplications(
                $request->application_ids,
                $request->only([
                    'admission_date',
                    'expected_graduation_date',
                ])
            );

            $message = "Batch conversion completed: {$result['success_count']} successful, {$result['error_count']} failed.";

            // Detailed success message with mapping info
            if ($result['success_count'] > 0) {
                $successDetails = [];
                foreach ($result['successful'] as $success) {
                    $student = $success['student'];
                    $application = $success['application'];
                    $successDetails[] = "✓ {$application['full_name']} → Student ID: {$student['student_id']}";
                }

                session()->flash('success_details', $successDetails);
            }

            // Detailed error information
            if ($result['error_count'] > 0) {
                $errorDetails = [];
                foreach ($result['failed'] as $failed) {
                    $errorDetails[] = [
                        'application_id' => $failed['application_id'],
                        'error' => $failed['error'],
                        'details' => $failed['errors'],
                    ];
                }

                return redirect()
                    ->back()
                    ->with('warning', $message)
                    ->with('batch_errors', $errorDetails)
                    ->with('conversion_summary', [
                        'total' => count($request->application_ids),
                        'successful' => $result['success_count'],
                        'failed' => $result['error_count'],
                    ]);
            }

            return redirect()
                ->route('student-applications.index')
                ->with('success', $message)
                ->with('conversion_summary', [
                    'total' => count($request->application_ids),
                    'successful' => $result['success_count'],
                    'failed' => $result['error_count'],
                ]);
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Batch conversion failed: ' . $e->getMessage())
                ->withInput();
        }
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
     * Check which applications are ready for batch conversion
     */
    public function checkConversionReadiness(Request $request): JsonResponse
    {
        $request->validate([
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'exists:student_applications,id',
        ]);

        $applications = StudentApplication::whereIn('id', $request->application_ids)->get();
        $ready = [];
        $notReady = [];

        foreach ($applications as $application) {
            $status = [
                'id' => $application->id,
                'full_name' => $application->full_name,
                'campus_code' => $application->campus_code,
                'intended_program' => $application->intended_program,
                'intake' => $application->intake,
            ];

            if ($application->isReadyForConversion()) {
                $mappingData = $application->getConversionMappingData();
                $status['mapping'] = [
                    'campus_id' => $mappingData['campus_id'] ?? null,
                    'program_id' => $mappingData['program_id'] ?? null,
                    'curriculum_version_id' => $mappingData['curriculum_version_id'] ?? null,
                ];
                $ready[] = $status;
            } else {
                $status['errors'] = $application->getConversionValidationErrors();
                $notReady[] = $status;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'ready' => $ready,
                'not_ready' => $notReady,
                'summary' => [
                    'total' => count($applications),
                    'ready_count' => count($ready),
                    'not_ready_count' => count($notReady),
                ],
            ],
        ]);
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

    /**
     * Show the import form
     */
    public function showImportForm()
    {
        return Inertia::render('student-applications/Import', [
            'maxFileSize' => '10MB',
            'allowedExtensions' => ['xlsx', 'xls', 'csv'],
        ]);
    }

    /**
     * Preview import data
     */
    public function previewImport(ImportStudentApplicationPreviewRequest $request): JsonResponse
    {
        try {
            $result = $this->importService->previewImport(
                $request->file('file'),
                $request->validated('options', [])
            );

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Student application import preview failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'file' => $request->file('file')?->getClientOriginalName(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process import
     */
    public function processImport(ImportStudentApplicationProcessRequest $request): JsonResponse
    {
        try {
            Log::info('Starting student application import processing', [
                'user_id' => auth()->user()?->id,
                'file_name' => $request->file('file')?->getClientOriginalName(),
                'file_size' => $request->file('file')?->getSize(),
                'column_mapping' => $request->validated('column_mapping'),
                'options' => $request->validated('options', []),
            ]);

            $result = $this->importService->processImport(
                $request->file('file'),
                $request->validated('column_mapping'),
                $request->validated('options', [])
            );

            Log::info('Student application import service completed', [
                'user_id' => auth()->user()?->id,
                'service_result' => $result,
                'file' => $request->file('file')?->getClientOriginalName(),
            ]);

            Log::info('Student application import completed', [
                'user_id' => auth()->user()?->id,
                'results' => $result['data'] ?? [],
                'file' => $request->file('file')?->getClientOriginalName(),
            ]);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Student application import processing failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->user()?->id,
                'file' => $request->file('file')?->getClientOriginalName(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download import template
     */
    public function downloadTemplate(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        try {
            $filePath = $this->importService->generateTemplate();
            $filename = 'student_applications_template_' . date('Y-m-d') . '.xlsx';

            return response()->download($filePath, $filename)->deleteFileAfterSend();
        } catch (\Exception $e) {
            Log::error('Failed to generate student application import template', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            abort(500, 'Failed to generate template: ' . $e->getMessage());
        }
    }

    /**
     * Export student applications to Excel or CSV
     */
    public function export(Request $request)
    {
        try {
            $request->validate([
                'format' => 'required|in:xlsx,csv',
                'scope' => 'required|in:filtered,all',
            ]);

            $filters = [
                'search' => $request->get('search'),
                'status' => $request->get('status'),
                'converted' => $request->get('converted'),
                'campus_code' => $request->get('campus_code'),
                'overall_operator' => $request->get('overall_operator'),
                'overall_value' => $request->get('overall_value'),
            ];

            $query = StudentApplication::query();

            if ($request->scope === 'filtered') {
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

                // Apply campus filter
                if ($filters['campus_code'] !== null && $filters['campus_code'] !== 'all') {
                    $query->where('campus_code', $filters['campus_code']);
                }

                // Apply overall score filter
                if ($filters['overall_operator'] && $filters['overall_value'] !== null) {
                    $operator = match ($filters['overall_operator']) {
                        'gt' => '>',
                        'gte' => '>=',
                        'lt' => '<',
                        'lte' => '<=',
                        'eq' => '=',
                        default => '='
                    };
                    $query->where('overall', $operator, $filters['overall_value']);
                }
            }

            // Apply sorting
            $sort = $request->get('sort', 'created_at');
            $direction = $request->get('direction', 'desc');
            $query->orderBy($sort, $direction);

            $export = new StudentApplicationExport($query, $filters);
            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "student_applications_{$timestamp}";

            if ($request->format === 'csv') {
                return ExcelFacade::download($export, "{$filename}.csv", Excel::CSV);
            }

            return ExcelFacade::download($export, "{$filename}.xlsx");
        } catch (\Exception $e) {
            Log::error('Export failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
