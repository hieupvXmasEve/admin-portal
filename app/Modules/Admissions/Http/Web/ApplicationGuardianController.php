<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\ApplicationGuardian;
use App\Models\StudentApplication;
use App\Modules\Admissions\Actions\CreateApplicantGuardianAction;
use App\Modules\Admissions\Actions\DeleteApplicantGuardianAction;
use App\Modules\Admissions\Actions\UpdateApplicantGuardianAction;
use App\Modules\Admissions\Exceptions\ApplicationLifecycleException;
use App\Modules\Admissions\Http\Requests\Admissions\StoreApplicantGuardianRequest;
use App\Modules\Admissions\Http\Requests\Admissions\UpdateApplicantGuardianRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ApplicationGuardianController extends Controller
{
    public function store(StoreApplicantGuardianRequest $request, StudentApplication $studentApplication): RedirectResponse
    {
        try {
            CreateApplicantGuardianAction::run(['application' => $studentApplication, 'attributes' => $request->validated()]);
        } catch (ApplicationLifecycleException $exception) {
            return $this->failure($studentApplication, $exception->getMessage());
        }

        $this->flash('success', 'Guardian added.');

        return redirect()->route('student-applications.show', $studentApplication);
    }

    public function update(UpdateApplicantGuardianRequest $request, StudentApplication $studentApplication, ApplicationGuardian $guardian): RedirectResponse
    {
        $this->belongsToApplication($studentApplication, $guardian);
        try {
            UpdateApplicantGuardianAction::run(['guardian' => $guardian, 'attributes' => $request->validated()]);
        } catch (ApplicationLifecycleException $exception) {
            return $this->failure($studentApplication, $exception->getMessage());
        }

        $this->flash('success', 'Guardian updated.');

        return redirect()->route('student-applications.show', $studentApplication);
    }

    public function destroy(StudentApplication $studentApplication, ApplicationGuardian $guardian): RedirectResponse
    {
        $this->belongsToApplication($studentApplication, $guardian);
        try {
            DeleteApplicantGuardianAction::run(['guardian' => $guardian]);
        } catch (ApplicationLifecycleException $exception) {
            return $this->failure($studentApplication, $exception->getMessage());
        }

        $this->flash('success', 'Guardian removed.');

        return redirect()->route('student-applications.show', $studentApplication);
    }

    private function belongsToApplication(StudentApplication $application, ApplicationGuardian $guardian): void
    {
        if ($guardian->student_application_id !== $application->id) {
            throw new NotFoundHttpException('Guardian not found for this application.');
        }
    }

    private function failure(StudentApplication $application, string $message): RedirectResponse
    {
        $this->flash('error', $message);

        return redirect()->route('student-applications.show', $application);
    }

    private function flash(string $key, string $message): void
    {
        Inertia::flash($key, $message);
        session()->flash($key, $message);
    }
}
