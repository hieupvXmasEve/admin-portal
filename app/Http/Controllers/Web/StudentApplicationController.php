<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exports\StudentApplicationExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentApplicationRequest;
use App\Http\Requests\UpdateStudentApplicationRequest;
use App\Models\ApplicationDocumentType;
use App\Models\ApplicationGuardian;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\StudentApplication;
use App\Services\ApplicationDocumentService;
use App\Services\StudentApplicationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use RuntimeException;
use Throwable;

class StudentApplicationController extends Controller
{
    public function __construct(
        private StudentApplicationService $studentApplicationService
    ) {}

    /**
     * Display a listing of student applications.
     */
    public function index(Request $request)
    {
        // Campus boundary: staff only see Applications for the campus they are
        // currently working in (the session campus bound by SetCampus).
        $currentCampus = $this->currentCampus();

        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'intake' => $request->get('intake'),
            'per_page' => min((int) $request->get('per_page', 15), 200),
            'sort' => $request->get('sort', 'created_at'),
            'direction' => $request->get('direction', 'desc'),
        ];

        $query = StudentApplication::query()
            ->with([
                'student' => function ($query) {
                    $query->select('id', 'student_id', 'full_name');
                },
                'documents' => function ($query) {
                    $query->orderBy('page_index')->orderBy('id');
                },
            ]);

        if ($currentCampus !== null) {
            $query->where('campus_code', $currentCampus->code);
        }

        if ($filters['search']) {
            $query->where(function ($q) use ($filters) {
                $q->where('full_name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('email', 'like', '%'.$filters['search'].'%')
                    ->orWhere('student_code', 'like', '%'.$filters['search'].'%')
                    ->orWhere('national_id', 'like', '%'.$filters['search'].'%')
                    ->orWhere('phone', 'like', '%'.$filters['search'].'%');
            });
        }

        if ($filters['status'] && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['intake'] !== null && $filters['intake'] !== '' && $filters['intake'] !== 'all') {
            $query->where('intake', $filters['intake']);
        }

        $query->orderBy($filters['sort'], $filters['direction']);

        $applications = $query
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (StudentApplication $application) => [
                'id' => $application->id,
                'full_name' => $application->full_name,
                'student_code' => $application->student_code,
                'email' => $application->email,
                'national_id' => $application->national_id,
                'phone' => $application->phone,
                'intended_program' => $application->intended_program,
                'intake' => $application->intake,
                'status' => $application->status,
                'created_at' => $application->created_at,
                'student' => $application->student,
                // Documents grouped by catalog code, so the list can render one
                // column per document type with the file links inline.
                'documents_by_type' => $application->documents
                    ->groupBy('file_type_code')
                    ->map(fn ($documents) => $documents->map(fn ($document) => [
                        'id' => $document->id,
                        'link' => $document->link,
                        'original_name' => $document->original_name,
                        'page_index' => $document->page_index,
                    ])->values()),
            ]);

        // The document-type catalog drives the per-type columns on the list.
        $documentTypes = ApplicationDocumentType::query()
            ->activeOrdered()
            ->get(['code', 'name']);

        $intakes = StudentApplication::query()
            ->when($currentCampus !== null, fn ($q) => $q->where('campus_code', $currentCampus->code))
            ->whereNotNull('intake')
            ->where('intake', '!=', '')
            ->distinct()
            ->orderBy('intake')
            ->pluck('intake');

        return Inertia::render('student-applications/index', [
            'applications' => $applications,
            'filters' => $filters,
            'currentCampus' => $currentCampus !== null
                ? ['code' => $currentCampus->code, 'name' => $currentCampus->name]
                : null,
            'documentTypes' => $documentTypes,
            'intakes' => $intakes,
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    /**
     * The campus the user is currently working in (bound by SetCampus from the
     * session), or null when none is selected.
     */
    private function currentCampus(): ?Campus
    {
        return app()->bound('campus') ? app('campus') : null;
    }

    /**
     * A focused, new-tab view of a single Application's documents grouped by type
     * (external links to the admissions catalog), with missing-required surfaced.
     */
    public function documents(StudentApplication $studentApplication, ApplicationDocumentService $documentService)
    {
        $studentApplication->load(['documents' => function ($query) {
            $query->orderBy('file_type_code')->orderBy('page_index')->orderBy('id');
        }]);

        return Inertia::render('student-applications/documents', [
            'application' => [
                'id' => $studentApplication->id,
                'full_name' => $studentApplication->full_name,
                'student_code' => $studentApplication->student_code,
                'campus_code' => $studentApplication->campus_code,
                'status' => $studentApplication->status,
                'is_international_applicant' => $studentApplication->is_international_applicant,
            ],
            'documentChecklist' => $documentService->checklist($studentApplication),
        ]);
    }

    /**
     * Show the form for creating a new student application.
     */
    public function create()
    {
        return Inertia::render('student-applications/create', $this->intentFormOptions());
    }

    /**
     * Store a newly created (pending) student application.
     */
    public function store(StoreStudentApplicationRequest $request)
    {
        $application = StudentApplication::create($request->validated());

        return redirect()
            ->route('student-applications.show', $application)
            ->with('success', 'Student application created successfully.');
    }

    /**
     * Display the specified student application.
     */
    public function show(StudentApplication $studentApplication, ApplicationDocumentService $documentService)
    {
        $studentApplication->load([
            'student' => function ($query) {
                $query->select('id', 'student_id', 'full_name', 'email', 'status');
            },
            'guardians' => function ($query) {
                $query->orderByDesc('is_primary')->orderBy('id');
            },
            'documents' => function ($query) {
                $query->orderBy('file_type_code')->orderBy('page_index')->orderBy('id');
            },
            'approvedByUser:id,name,email',
            'rejectedByUser:id,name,email',
            'revokedByUser:id,name,email',
        ]);

        return Inertia::render('student-applications/show', [
            'application' => $studentApplication,
            'guardianRelationships' => ApplicationGuardian::relationships(),
            'documentChecklist' => $documentService->checklist($studentApplication),
        ]);
    }

    /**
     * Show the form for editing the specified student application.
     */
    public function edit(StudentApplication $studentApplication)
    {
        return Inertia::render('student-applications/edit', [
            'application' => $studentApplication,
            ...$this->intentFormOptions(),
        ]);
    }

    /**
     * Dropdown options for the manual create/edit form. Programs, campuses and
     * intakes are the canonical code lists the form submits codes from (ADR-0005).
     *
     * @return array{campuses: Collection<int, Campus>, programs: Collection<int, Program>, semesters: Collection<int, Semester>}
     */
    private function intentFormOptions(): array
    {
        return [
            'campuses' => Campus::select('id', 'code', 'name')->orderBy('name')->get(),
            'programs' => Program::select('id', 'code', 'name')->orderBy('name')->get(),
            'semesters' => Semester::select('id', 'code', 'name')->orderBy('code')->get(),
        ];
    }

    /**
     * Update the specified student application.
     */
    public function update(UpdateStudentApplicationRequest $request, StudentApplication $studentApplication)
    {
        $studentApplication->update($request->validated());

        return redirect()
            ->route('student-applications.show', $studentApplication)
            ->with('success', 'Student application updated successfully.');
    }

    /**
     * Remove the specified student application.
     */
    public function destroy(StudentApplication $studentApplication)
    {
        if ($studentApplication->isConverted()) {
            return redirect()
                ->route('student-applications.index')
                ->with('error', 'Cannot delete an application that is linked to a student.');
        }

        $studentApplication->delete();

        return redirect()
            ->route('student-applications.index')
            ->with('success', 'Student application deleted successfully.');
    }

    /**
     * Approve a pending application: atomically create the enrolled student.
     */
    public function approve(Request $request, StudentApplication $studentApplication): RedirectResponse
    {
        $validated = $request->validate([
            'admission_date' => 'nullable|date',
            'expected_graduation_date' => 'nullable|date|after:admission_date',
        ]);

        try {
            $this->studentApplicationService->approve(
                $studentApplication,
                $request->user(),
                $validated
            );
        } catch (QueryException $e) {
            // A persistence failure (e.g. duplicate student code) rolled the whole
            // approval back — log the detail, never surface raw SQL to the user.
            Log::warning('Application approval failed (database)', [
                'application_id' => $studentApplication->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to approve the application. Please verify the data and try again.');
        } catch (RuntimeException $e) {
            // Domain guard messages (e.g. "Only a pending application…") are safe.
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::warning('Application approval failed', [
                'application_id' => $studentApplication->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to approve the application. Please try again.');
        }

        return redirect()
            ->route('student-applications.show', $studentApplication)
            ->with('success', 'Application approved. The student has been enrolled.');
    }

    /**
     * Reject a pending application with a reason; no student is created.
     */
    public function reject(Request $request, StudentApplication $studentApplication): RedirectResponse
    {
        $validated = $request->validate([
            'rejected_reason' => 'required|string|max:1000',
        ]);

        try {
            $this->studentApplicationService->reject(
                $studentApplication,
                $request->user(),
                $validated['rejected_reason']
            );
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::warning('Application rejection failed', [
                'application_id' => $studentApplication->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to reject the application. Please try again.');
        }

        return redirect()
            ->route('student-applications.show', $studentApplication)
            ->with('success', 'Application rejected.');
    }

    /**
     * Revoke a mistaken approval within the safe window: tear down the created
     * Student + User + roles and return the application to `pending`.
     */
    public function revoke(Request $request, StudentApplication $studentApplication): RedirectResponse
    {
        try {
            $this->studentApplicationService->revoke(
                $studentApplication,
                $request->user()
            );
        } catch (RuntimeException $e) {
            // Domain guard messages (not enrolled / has activity → use Withdraw)
            // are safe to surface and nothing was changed.
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::warning('Application revoke failed', [
                'application_id' => $studentApplication->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Failed to revoke the application. Please try again.');
        }

        return redirect()
            ->route('student-applications.show', $studentApplication)
            ->with('success', 'Approval revoked. The application is pending again.');
    }

    /**
     * Export student applications to Excel or CSV.
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
                'campus_code' => $request->get('campus_code'),
                'intake' => $request->get('intake'),
            ];

            $query = StudentApplication::query();

            // Never export beyond the user's current campus boundary.
            $currentCampus = $this->currentCampus();
            if ($currentCampus !== null) {
                $query->where('campus_code', $currentCampus->code);
            }

            if ($request->scope === 'filtered') {
                if ($filters['search']) {
                    $query->where(function ($q) use ($filters) {
                        $q->where('full_name', 'like', '%'.$filters['search'].'%')
                            ->orWhere('email', 'like', '%'.$filters['search'].'%')
                            ->orWhere('national_id', 'like', '%'.$filters['search'].'%')
                            ->orWhere('phone', 'like', '%'.$filters['search'].'%');
                    });
                }

                if ($filters['status'] && $filters['status'] !== 'all') {
                    $query->where('status', $filters['status']);
                }

                if ($filters['campus_code'] !== null && $filters['campus_code'] !== 'all') {
                    $query->where('campus_code', $filters['campus_code']);
                }

                if ($filters['intake'] !== null && $filters['intake'] !== '' && $filters['intake'] !== 'all') {
                    $query->where('intake', $filters['intake']);
                }
            }

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
        } catch (Throwable $e) {
            Log::error('Export failed: '.$e->getMessage());

            return response()->json([
                'error' => 'Export failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Status filter options for the staff UI.
     *
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => StudentApplication::STATUS_PENDING, 'label' => 'Pending'],
            ['value' => StudentApplication::STATUS_ENROLLED, 'label' => 'Enrolled'],
            ['value' => StudentApplication::STATUS_REJECTED, 'label' => 'Rejected'],
        ];
    }
}
