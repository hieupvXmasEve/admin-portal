<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exports\StudentApplicationExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentApplicationRequest;
use App\Http\Requests\UpdateStudentApplicationRequest;
use App\Models\ApplicationGuardian;
use App\Models\Campus;
use App\Models\Program;
use App\Models\StudentApplication;
use App\Services\StudentApplicationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'campus_code' => $request->get('campus_code'),
            'intake' => $request->get('intake'),
            'per_page' => min((int) $request->get('per_page', 15), 200),
            'sort' => $request->get('sort', 'created_at'),
            'direction' => $request->get('direction', 'desc'),
        ];

        $query = StudentApplication::query()
            ->with(['student' => function ($query) {
                $query->select('id', 'student_id', 'full_name');
            }]);

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

        if ($filters['campus_code'] !== null && $filters['campus_code'] !== 'all') {
            $query->where('campus_code', $filters['campus_code']);
        }

        if ($filters['intake'] !== null && $filters['intake'] !== '' && $filters['intake'] !== 'all') {
            $query->where('intake', $filters['intake']);
        }

        $query->orderBy($filters['sort'], $filters['direction']);

        $applications = $query
            ->paginate($filters['per_page'])
            ->withQueryString();

        $campuses = Campus::select('code', 'name')->get();
        $intakes = StudentApplication::query()
            ->whereNotNull('intake')
            ->where('intake', '!=', '')
            ->distinct()
            ->orderBy('intake')
            ->pluck('intake');

        return Inertia::render('student-applications/index', [
            'applications' => $applications,
            'filters' => $filters,
            'campuses' => $campuses,
            'intakes' => $intakes,
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    /**
     * Show the form for creating a new student application.
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
    public function show(StudentApplication $studentApplication)
    {
        $studentApplication->load([
            'student' => function ($query) {
                $query->select('id', 'student_id', 'full_name', 'email', 'status');
            },
            'guardians' => function ($query) {
                $query->orderByDesc('is_primary')->orderBy('id');
            },
            'approvedByUser:id,name,email',
            'rejectedByUser:id,name,email',
            'revokedByUser:id,name,email',
        ]);

        return Inertia::render('student-applications/show', [
            'application' => $studentApplication,
            'guardianRelationships' => ApplicationGuardian::relationships(),
        ]);
    }

    /**
     * Show the form for editing the specified student application.
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
