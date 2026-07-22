<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\ApplicationGuardian;
use App\Models\StudentApplication;
use App\Modules\Admissions\Actions\ManageApplicantGuardianAction;
use App\Modules\Admissions\Http\Requests\StoreApplicantGuardianRequest;
use App\Modules\Admissions\Http\Requests\UpdateApplicantGuardianRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ApplicationGuardianController extends Controller
{
    public function __construct(private readonly ManageApplicantGuardianAction $guardians) {}

    public function store(StoreApplicantGuardianRequest $request, StudentApplication $studentApplication): RedirectResponse
    {
        if (! $studentApplication->isPending()) {
            return $this->frozen($studentApplication);
        }
        ManageApplicantGuardianAction::run(['operation' => 'create', 'application' => $studentApplication, 'attributes' => $request->validated()]);

        $this->flash('success', 'Guardian added.');

        return redirect()->route('student-applications.show', $studentApplication);
    }

    public function update(UpdateApplicantGuardianRequest $request, StudentApplication $studentApplication, ApplicationGuardian $guardian): RedirectResponse
    {
        $this->belongsToApplication($studentApplication, $guardian);
        if (! $studentApplication->isPending()) {
            return $this->frozen($studentApplication);
        }
        ManageApplicantGuardianAction::run(['operation' => 'update', 'guardian' => $guardian, 'attributes' => $request->validated()]);

        $this->flash('success', 'Guardian updated.');

        return redirect()->route('student-applications.show', $studentApplication);
    }

    public function destroy(StudentApplication $studentApplication, ApplicationGuardian $guardian): RedirectResponse
    {
        $this->belongsToApplication($studentApplication, $guardian);
        if (! $studentApplication->isPending()) {
            return $this->frozen($studentApplication);
        }
        ManageApplicantGuardianAction::run(['operation' => 'delete', 'guardian' => $guardian]);

        $this->flash('success', 'Guardian removed.');

        return redirect()->route('student-applications.show', $studentApplication);
    }

    private function belongsToApplication(StudentApplication $application, ApplicationGuardian $guardian): void
    {
        if ($guardian->student_application_id !== $application->id) {
            throw new NotFoundHttpException('Guardian not found for this application.');
        }
    }

    private function frozen(StudentApplication $application): RedirectResponse
    {
        $this->flash('error', 'Guardians can only be changed while the application is pending.');

        return redirect()->route('student-applications.show', $application);
    }

    private function flash(string $key, string $message): void
    {
        Inertia::flash($key, $message);
        session()->flash($key, $message);
    }
}
