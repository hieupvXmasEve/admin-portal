<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicationGuardianRequest;
use App\Http\Requests\UpdateApplicationGuardianRequest;
use App\Models\ApplicationGuardian;
use App\Models\StudentApplication;
use App\Services\ApplicationGuardianService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Staff CRUD for Guardians on a pending Application.
 *
 * Guardians can only be changed while the Application is `pending`; once it is
 * enrolled or rejected the record is frozen (ADR-0003) and changes are refused.
 */
class ApplicationGuardianController extends Controller
{
    public function __construct(
        private ApplicationGuardianService $guardianService
    ) {}

    /**
     * Add a Guardian to a pending Application.
     */
    public function store(
        StoreApplicationGuardianRequest $request,
        StudentApplication $studentApplication
    ): RedirectResponse {
        if (! $studentApplication->isPending()) {
            return $this->frozenRedirect($studentApplication);
        }

        $this->guardianService->create($studentApplication, $request->validated());

        return redirect()
            ->route('student-applications.show', $studentApplication)
            ->with('success', 'Guardian added.');
    }

    /**
     * Update a Guardian on a pending Application.
     */
    public function update(
        UpdateApplicationGuardianRequest $request,
        StudentApplication $studentApplication,
        ApplicationGuardian $guardian
    ): RedirectResponse {
        $this->ensureGuardianBelongsToApplication($studentApplication, $guardian);

        if (! $studentApplication->isPending()) {
            return $this->frozenRedirect($studentApplication);
        }

        $this->guardianService->update($guardian, $request->validated());

        return redirect()
            ->route('student-applications.show', $studentApplication)
            ->with('success', 'Guardian updated.');
    }

    /**
     * Remove a Guardian from a pending Application.
     */
    public function destroy(
        StudentApplication $studentApplication,
        ApplicationGuardian $guardian
    ): RedirectResponse {
        $this->ensureGuardianBelongsToApplication($studentApplication, $guardian);

        if (! $studentApplication->isPending()) {
            return $this->frozenRedirect($studentApplication);
        }

        $this->guardianService->delete($guardian);

        return redirect()
            ->route('student-applications.show', $studentApplication)
            ->with('success', 'Guardian removed.');
    }

    /**
     * Guard against tampering with a Guardian id from a different Application.
     */
    private function ensureGuardianBelongsToApplication(
        StudentApplication $studentApplication,
        ApplicationGuardian $guardian
    ): void {
        if ($guardian->student_application_id !== $studentApplication->id) {
            throw new NotFoundHttpException('Guardian not found for this application.');
        }
    }

    private function frozenRedirect(StudentApplication $studentApplication): RedirectResponse
    {
        return redirect()
            ->route('student-applications.show', $studentApplication)
            ->with('error', 'Guardians can only be changed while the application is pending.');
    }
}
