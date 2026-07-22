<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\StudentApplicationController as LegacyStudentApplicationController;
use App\Http\Requests\StoreStudentApplicationRequest;
use App\Http\Requests\UpdateStudentApplicationRequest;
use App\Models\ApplicationGuardian;
use App\Models\StudentApplication;
use App\Modules\Admissions\Actions\ApproveApplicationAction;
use App\Modules\Admissions\Actions\RejectApplicationAction;
use App\Modules\Admissions\Actions\RevokeApplicationAction;
use App\Modules\Admissions\Exceptions\ApplicationLifecycleException;
use App\Modules\Admissions\Http\Requests\ApproveApplicationRequest;
use App\Modules\Admissions\Http\Requests\RejectApplicationRequest;
use App\Modules\Admissions\Queries\GetApplicantDocumentChecklistQuery;
use App\Services\StudentApplicationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Throwable;

/** Admissions-owned staff adapter for lifecycle mutations. */
final class StudentApplicationController extends Controller
{
    private readonly LegacyStudentApplicationController $legacyController;

    public function __construct(
        StudentApplicationService $legacyService,
        private readonly ApproveApplicationAction $approveApplication,
        private readonly RejectApplicationAction $rejectApplication,
        private readonly RevokeApplicationAction $revokeApplication,
    ) {
        $this->legacyController = new LegacyStudentApplicationController($legacyService);
    }

    public function index(Request $request): mixed
    {
        return $this->legacyController->index($request);
    }

    public function documents(StudentApplication $studentApplication, GetApplicantDocumentChecklistQuery $documentChecklist): mixed
    {
        $studentApplication->load(['documents' => fn ($query) => $query->orderBy('file_type_code')->orderBy('page_index')->orderBy('id')]);

        return Inertia::render('student-applications/documents', [
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
        return $this->legacyController->create();
    }

    public function store(StoreStudentApplicationRequest $request): mixed
    {
        return $this->legacyController->store($request);
    }

    public function show(StudentApplication $studentApplication, GetApplicantDocumentChecklistQuery $documentChecklist): mixed
    {
        $studentApplication->load([
            'student:id,student_id,full_name,email,status',
            'guardians' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('id'),
            'documents' => fn ($query) => $query->orderBy('file_type_code')->orderBy('page_index')->orderBy('id'),
            'approvedByUser:id,name,email',
            'rejectedByUser:id,name,email',
            'revokedByUser:id,name,email',
        ]);

        return Inertia::render('student-applications/show', [
            'application' => $studentApplication,
            'guardianRelationships' => ApplicationGuardian::relationships(),
            'documentChecklist' => $documentChecklist->handle($studentApplication),
        ]);
    }

    public function edit(StudentApplication $studentApplication): mixed
    {
        return $this->legacyController->edit($studentApplication);
    }

    public function update(UpdateStudentApplicationRequest $request, StudentApplication $studentApplication): mixed
    {
        return $this->legacyController->update($request, $studentApplication);
    }

    public function destroy(StudentApplication $studentApplication): mixed
    {
        return $this->legacyController->destroy($studentApplication);
    }

    public function export(Request $request): mixed
    {
        return $this->legacyController->export($request);
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

    public function revoke(Request $request, StudentApplication $studentApplication): RedirectResponse
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
