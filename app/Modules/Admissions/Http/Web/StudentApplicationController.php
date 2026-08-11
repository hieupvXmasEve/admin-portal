<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\ApplicationGuardian;
use App\Models\StudentApplication;
use App\Modules\Admissions\Actions\ApproveApplicationAction;
use App\Modules\Admissions\Actions\CreateApplicationAction;
use App\Modules\Admissions\Actions\DeleteApplicationAction;
use App\Modules\Admissions\Actions\ExportApplicationsAction;
use App\Modules\Admissions\Actions\RejectApplicationAction;
use App\Modules\Admissions\Actions\RevokeApplicationAction;
use App\Modules\Admissions\Actions\UpdateApplicationAction;
use App\Modules\Admissions\Exceptions\ApplicationLifecycleException;
use App\Modules\Admissions\Http\Requests\Admissions\ApproveApplicationRequest;
use App\Modules\Admissions\Http\Requests\Admissions\ExportApplicationsRequest;
use App\Modules\Admissions\Http\Requests\Admissions\ListApplicationsRequest;
use App\Modules\Admissions\Http\Requests\Admissions\RejectApplicationRequest;
use App\Modules\Admissions\Http\Requests\Admissions\RevokeApplicationRequest;
use App\Modules\Admissions\Http\Requests\Admissions\StoreApplicationRequest;
use App\Modules\Admissions\Http\Requests\Admissions\UpdateApplicationRequest;
use App\Modules\Admissions\Queries\GetApplicantDocumentChecklistQuery;
use App\Modules\Admissions\Queries\GetApplicationConversionReadinessQuery;
use App\Modules\Admissions\Queries\ListApplicationsQuery;
use App\Shared\Contracts\Admissions\ApplicationProgramMappingReader;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;

/** Admissions-owned staff adapter for lifecycle mutations. */
final class StudentApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationProgramMappingReader $programMappingReader,
    ) {}

    public function index(ListApplicationsRequest $request, ListApplicationsQuery $applications): mixed
    {
        $filters = array_merge([
            'search' => $request->validated('search'),
            'status' => $request->validated('status'),
            'intake' => $request->validated('intake'),
            'per_page' => $request->validated('per_page', 15),
            'sort' => $request->validated('sort', 'created_at'),
            'direction' => $request->validated('direction', 'desc'),
        ], collect(ListApplicationsQuery::ADVANCED_FILTER_KEYS)->mapWithKeys(
            fn (string $key) => [$key => $request->validated($key)],
        )->all());
        $campus = app()->bound('campus') ? app('campus') : null;
        $lists = $applications->filters($campus?->code);

        return Inertia::render('StudentApplications/Index', [
            'applications' => $applications->handle($filters, $campus?->code),
            'filters' => $filters,
            'currentCampus' => $campus === null ? null : ['code' => $campus->code, 'name' => $campus->name],
            'documentTypes' => $lists['document_types'],
            'intakes' => $lists['intakes'],
            // Closure: skipped on the `only: ['applications', 'filters']` partial
            // reload every keystroke triggers, so the DISTINCT scans behind it
            // don't run on requests that never render the filter panel.
            'filterOptions' => fn () => $applications->filterOptions($campus?->code),
            'statusOptions' => [['value' => StudentApplication::STATUS_PENDING, 'label' => 'Pending'], ['value' => StudentApplication::STATUS_ENROLLED, 'label' => 'Enrolled'], ['value' => StudentApplication::STATUS_REJECTED, 'label' => 'Rejected']],
        ]);
    }

    public function documents(StudentApplication $studentApplication, GetApplicantDocumentChecklistQuery $documentChecklist): mixed
    {
        $studentApplication->load(['documents' => fn ($query) => $query->orderBy('file_type_code')->orderBy('page_index')->orderBy('id')]);

        return Inertia::render('StudentApplications/Documents', [
            'application' => [
                'id' => $studentApplication->id,
                'full_name' => $studentApplication->full_name,
                'student_code' => $studentApplication->student_code,
                'campus_code' => $studentApplication->campus_code,
                'status' => $studentApplication->status,
                'is_international_applicant' => $studentApplication->is_international_applicant,
            ],
            'documentChecklist' => $documentChecklist->handle($studentApplication),
        ]);
    }

    public function create(): mixed
    {
        return Inertia::render('StudentApplications/Create', $this->programMappingReader->formOptions());
    }

    public function store(StoreApplicationRequest $request): RedirectResponse
    {
        $application = CreateApplicationAction::run($request->validated());
        $this->flash('success', 'Student application created successfully.');

        return redirect()->route('student-applications.show', $application);
    }

    public function show(StudentApplication $studentApplication, GetApplicantDocumentChecklistQuery $documentChecklist, GetApplicationConversionReadinessQuery $readiness): mixed
    {
        $studentApplication->load([
            'student:id,student_id,full_name,email,status',
            'guardians' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('id'),
            'documents' => fn ($query) => $query->orderBy('file_type_code')->orderBy('page_index')->orderBy('id'),
            'approvedByUser:id,name,email',
            'rejectedByUser:id,name,email',
            'revokedByUser:id,name,email',
        ]);

        return Inertia::render('StudentApplications/Show', [
            'application' => $studentApplication,
            'guardianRelationships' => ApplicationGuardian::relationships(),
            'documentChecklist' => $documentChecklist->handle($studentApplication),
            'conversionReadiness' => $studentApplication->isPending() ? $readiness->handle($studentApplication) : null,
        ]);
    }

    public function edit(StudentApplication $studentApplication): mixed
    {
        return Inertia::render('StudentApplications/Edit', ['application' => $studentApplication, ...$this->programMappingReader->formOptions()]);
    }

    public function update(UpdateApplicationRequest $request, StudentApplication $studentApplication): RedirectResponse
    {
        UpdateApplicationAction::run(['application' => $studentApplication, 'attributes' => $request->validated()]);
        $this->flash('success', 'Student application updated successfully.');

        return redirect()->route('student-applications.show', $studentApplication);
    }

    public function destroy(StudentApplication $studentApplication): mixed
    {
        try {
            DeleteApplicationAction::run(['application' => $studentApplication]);
        } catch (ApplicationLifecycleException $exception) {
            $this->flash('error', $exception->getMessage());

            return redirect()->route('student-applications.index');
        }
        $this->flash('success', 'Student application deleted successfully.');

        return redirect()->route('student-applications.index');
    }

    public function export(ExportApplicationsRequest $request): mixed
    {
        $campus = app()->bound('campus') ? app('campus') : null;

        try {
            return ExportApplicationsAction::run([...$request->validated(), 'sort' => $request->validated('sort', 'created_at'), 'direction' => $request->validated('direction', 'desc'), 'current_campus_code' => $campus?->code]);
        } catch (Throwable $exception) {
            Log::error('Export failed: '.$exception->getMessage());
            $this->flash('error', 'Export failed. Please try again.');

            return redirect()->back();
        }
    }

    public function approve(ApproveApplicationRequest $request, StudentApplication $studentApplication): RedirectResponse
    {
        try {
            ApproveApplicationAction::run([
                'application' => $studentApplication,
                'actor_id' => (int) $request->user()->id,
                'options' => $request->validated(),
            ]);
        } catch (QueryException $exception) {
            Log::warning('Application approval failed (database)', ['application_id' => $studentApplication->id, 'error' => $exception->getMessage()]);

            $this->flash('error', 'Failed to approve the application. Please verify the data and try again.');

            return redirect()->back();
        } catch (ApplicationLifecycleException $exception) {
            $this->flash('error', $exception->getMessage());

            return redirect()->back();
        } catch (Throwable $exception) {
            Log::warning('Application approval failed', ['application_id' => $studentApplication->id, 'error' => $exception->getMessage()]);

            $this->flash('error', 'Failed to approve the application. Please try again.');

            return redirect()->back();
        }

        $this->flash('success', 'Application approved. The student has been enrolled.');

        return redirect()->back();
    }

    public function reject(RejectApplicationRequest $request, StudentApplication $studentApplication): RedirectResponse
    {
        try {
            RejectApplicationAction::run([
                'application' => $studentApplication,
                'actor_id' => (int) $request->user()->id,
                'reason' => $request->validated('rejected_reason'),
            ]);
        } catch (ApplicationLifecycleException $exception) {
            $this->flash('error', $exception->getMessage());

            return redirect()->back();
        } catch (Throwable $exception) {
            Log::warning('Application rejection failed', ['application_id' => $studentApplication->id, 'error' => $exception->getMessage()]);

            $this->flash('error', 'Failed to reject the application. Please try again.');

            return redirect()->back();
        }

        $this->flash('success', 'Application rejected.');

        return redirect()->back();
    }

    public function revoke(RevokeApplicationRequest $request, StudentApplication $studentApplication): RedirectResponse
    {
        try {
            RevokeApplicationAction::run(['application' => $studentApplication, 'actor_id' => (int) $request->user()->id]);
        } catch (ApplicationLifecycleException $exception) {
            $this->flash('error', $exception->getMessage());

            return redirect()->back();
        } catch (Throwable $exception) {
            Log::warning('Application revoke failed', ['application_id' => $studentApplication->id, 'error' => $exception->getMessage()]);

            $this->flash('error', 'Failed to revoke the application. Please try again.');

            return redirect()->back();
        }

        $this->flash('success', 'Approval revoked. The application is pending again.');

        return redirect()->back();
    }

    private function flash(string $key, string $message): void
    {
        Inertia::flash($key, $message);
        session()->flash($key, $message);
    }
}
