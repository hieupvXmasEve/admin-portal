<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web\Admin;

use App\Constants\CourseRegistrationRoutes;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Modules\Academic\Delivery\Actions\DropCourseRegistrationAction;
use App\Modules\Academic\Delivery\Actions\RegisterStudentForActiveSemesterUnitsAction;
use App\Modules\Academic\Delivery\Actions\RemoveStudentFromCourseOfferingAction;
use App\Modules\Academic\Delivery\Actions\UpdateCourseRegistrationStatusAction;
use App\Modules\Academic\Delivery\Actions\WithdrawCourseRegistrationAction;
use App\Modules\Academic\Delivery\Http\Requests\BulkDestroyCourseRegistrationsRequest;
use App\Modules\Academic\Delivery\Http\Requests\CheckCourseRegistrationEligibilityRequest;
use App\Modules\Academic\Delivery\Http\Requests\GetAvailableCourseRegistrationsRequest;
use App\Modules\Academic\Delivery\Http\Requests\GetAvailableCourseRegistrationUnitsRequest;
use App\Modules\Academic\Delivery\Http\Requests\ListCourseRegistrationsRequest;
use App\Modules\Academic\Delivery\Http\Requests\ListStudentCourseRegistrationsRequest;
use App\Modules\Academic\Delivery\Http\Requests\ShowCourseRegistrationFormRequest;
use App\Modules\Academic\Delivery\Http\Requests\StoreCourseRegistrationRequest;
use App\Modules\Academic\Delivery\Http\Requests\UpdateCourseRegistrationRequest;
use App\Modules\Academic\Delivery\Queries\CheckCourseRegistrationEligibilityQuery;
use App\Modules\Academic\Delivery\Queries\GetAvailableCourseRegistrationsQuery;
use App\Modules\Academic\Delivery\Queries\GetCourseRegistrationFormOptionsQuery;
use App\Modules\Academic\Delivery\Queries\ListCourseRegistrationsQuery;
use App\Modules\Academic\Delivery\Queries\ListStudentCourseRegistrationsQuery;
use App\Modules\Academic\Delivery\Support\CourseRegistrationPresenter;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CourseRegistrationController extends Controller
{
    public function __construct(
        private readonly ListCourseRegistrationsQuery $registrations,
        private readonly GetCourseRegistrationFormOptionsQuery $formOptions,
        private readonly GetAvailableCourseRegistrationsQuery $availableCourses,
        private readonly CheckCourseRegistrationEligibilityQuery $eligibility,
        private readonly ListStudentCourseRegistrationsQuery $studentRegistrations,
        private readonly CourseOfferingCatalogReader $catalog,
        private readonly CourseRegistrationPresenter $presenter,
    ) {}

    public function index(ListCourseRegistrationsRequest $request): Response
    {
        $filters = $request->validated();
        $result = $this->registrations->handle($filters, $this->campusId());

        return Inertia::render('CourseRegistrations/Index', [
            'registrations' => $result['registrations'],
            'statistics' => $result['statistics'],
            'filters' => $request->only(['search', 'semester_id', 'status', 'course_offering_id', 'per_page']),
            'semesters' => $result['semesters'],
            'courseOfferings' => $result['courseOfferings'],
            'statusOptions' => $result['statusOptions'],
        ]);
    }

    public function create(ShowCourseRegistrationFormRequest $request): Response
    {
        $semesterId = $request->integer('semester_id') ?: null;

        return Inertia::render('CourseRegistrations/Create', $this->formOptions->handle($semesterId, $this->campusId()));
    }

    public function store(StoreCourseRegistrationRequest $request): RedirectResponse
    {
        try {
            $result = RegisterStudentForActiveSemesterUnitsAction::run($request->validated(), $this->campusId());
            if ($result['registered_count'] === 0) {
                Inertia::flash('error', 'No units were registered. Errors: '.implode('; ', $result['errors']));

                return Redirect::back()->withInput();
            }

            $message = "Successfully registered student for {$result['registered_count']} unit(s).";
            if ($result['errors'] !== []) {
                $message .= ' Some units could not be registered: '.implode('; ', $result['errors']);
            }

            Inertia::flash('success', $message);

            return Redirect::route(CourseRegistrationRoutes::INDEX);
        } catch (NotFoundHttpException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Inertia::flash('error', $exception->getMessage());

            return Redirect::back()->withInput();
        }
    }

    public function show(CourseRegistration $adminCourseRegistration): Response
    {
        $this->assertRegistrationInCampus($adminCourseRegistration);

        return Inertia::render('CourseRegistrations/Show', ['registration' => $this->presenter->registration($adminCourseRegistration)]);
    }

    public function edit(CourseRegistration $adminCourseRegistration): Response
    {
        $this->assertRegistrationInCampus($adminCourseRegistration);

        return Inertia::render('CourseRegistrations/Edit', [
            'registration' => $this->presenter->registration($adminCourseRegistration),
            'canEditStatus' => $this->canEditRegistrationStatus($adminCourseRegistration),
        ]);
    }

    public function update(UpdateCourseRegistrationRequest $request, CourseRegistration $adminCourseRegistration): RedirectResponse
    {
        try {
            $this->assertRegistrationInCampus($adminCourseRegistration);
            if (! $this->canEditRegistrationStatus($adminCourseRegistration)
                && $request->validated('registration_status') !== $adminCourseRegistration->registration_status) {
                Inertia::flash('error', 'Registration status can only be changed during the course registration period.');

                return Redirect::back()->withInput();
            }

            UpdateCourseRegistrationStatusAction::run([
                'course_registration_id' => $adminCourseRegistration->id,
                'campus_id' => $this->campusId(),
                ...$request->validated(),
            ]);

            Inertia::flash('success', 'Course registration updated successfully.');

            return Redirect::route(CourseRegistrationRoutes::SHOW, $adminCourseRegistration);
        } catch (NotFoundHttpException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Inertia::flash('error', 'Failed to update course registration: '.$exception->getMessage());

            return Redirect::back()->withInput();
        }
    }

    public function destroy(CourseRegistration $adminCourseRegistration): RedirectResponse
    {
        try {
            $this->assertRegistrationInCampus($adminCourseRegistration);
            RemoveStudentFromCourseOfferingAction::run($adminCourseRegistration->id, $this->campusId());
            Inertia::flash('success', 'Course registration deleted successfully.');

            return Redirect::route(CourseRegistrationRoutes::INDEX);
        } catch (NotFoundHttpException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Inertia::flash('error', 'Failed to delete course registration: '.$exception->getMessage());

            return Redirect::back();
        }
    }

    public function bulkDestroy(BulkDestroyCourseRegistrationsRequest $request): RedirectResponse
    {
        try {
            RemoveStudentFromCourseOfferingAction::runMany(array_map('intval', $request->validated('ids')), $this->campusId());
            Inertia::flash('success', 'Selected course registrations deleted successfully.');

            return Redirect::route(CourseRegistrationRoutes::INDEX);
        } catch (\Throwable $exception) {
            Inertia::flash('error', 'Failed to delete course registrations: '.$exception->getMessage());

            return Redirect::back();
        }
    }

    public function drop(CourseRegistration $adminCourseRegistration): RedirectResponse
    {
        try {
            $this->assertRegistrationInCampus($adminCourseRegistration);
            DropCourseRegistrationAction::run($adminCourseRegistration->id, $this->campusId());
            Inertia::flash('success', 'Student dropped from course successfully.');
        } catch (\Throwable $exception) {
            Inertia::flash('error', $exception->getMessage());
        }

        return Redirect::back();
    }

    public function withdraw(CourseRegistration $adminCourseRegistration): RedirectResponse
    {
        try {
            $this->assertRegistrationInCampus($adminCourseRegistration);
            WithdrawCourseRegistrationAction::run($adminCourseRegistration->id, $this->campusId());
            Inertia::flash('success', 'Student withdrawn from course successfully.');
        } catch (\Throwable $exception) {
            Inertia::flash('error', $exception->getMessage());
        }

        return Redirect::back();
    }

    public function getAvailableCourses(GetAvailableCourseRegistrationsRequest $request): JsonResponse
    {
        try {
            return ApiResponse::compatible([
                'success' => true,
                'data' => $this->availableCourses->handle($request->integer('student_id'), $request->integer('semester_id'), $this->campusId()),
            ]);
        } catch (NotFoundHttpException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            return ApiResponse::compatible(['success' => false, 'message' => $exception->getMessage()], 400);
        }
    }

    public function getAvailableUnits(GetAvailableCourseRegistrationUnitsRequest $request): JsonResponse
    {
        try {
            $activeSemester = $this->catalog->currentOfferingPeriod();
            if ($activeSemester === null) {
                return ApiResponse::compatible(['success' => false, 'message' => 'No active semester found'], 400);
            }

            $availableCourses = $this->availableCourses->handle($request->integer('student_id'), (int) $activeSemester->id, $this->campusId());
            $units = [];
            foreach ($availableCourses as $courseData) {
                $offering = $courseData['offering'];
                $unit = $offering['unit'];
                if (! isset($units[$unit['id']])) {
                    $units[$unit['id']] = [
                        'unit' => $unit,
                        'offerings' => [],
                        'is_eligible' => true,
                        'is_already_registered' => false,
                        'reasons' => [],
                    ];
                }

                $units[$unit['id']]['offerings'][] = $offering;
                if (! $courseData['eligible']) {
                    $units[$unit['id']]['is_eligible'] = false;
                    $units[$unit['id']]['reasons'] = array_merge($units[$unit['id']]['reasons'], $courseData['reasons']);
                }
            }

            $registeredUnits = [];
            $registeredUnitIds = [];
            $registrations = $this->studentRegistrations->handle($request->integer('student_id'), (int) $activeSemester->id, $this->campusId());
            foreach ($registrations as $registration) {
                if (! in_array($registration['registration_status'], ['registered', 'confirmed'], true)) {
                    continue;
                }

                $unit = $registration['course_offering']['unit'];
                $registeredUnitIds[] = $unit['id'];
                $registeredUnits[] = [
                    'unit' => $unit,
                    'offerings' => [$registration['course_offering']],
                    'is_eligible' => false,
                    'is_already_registered' => true,
                    'reasons' => ['Already registered for this unit'],
                ];
            }

            foreach ($registeredUnitIds as $unitId) {
                if (isset($units[$unitId])) {
                    $units[$unitId]['is_already_registered'] = true;
                    $units[$unitId]['is_eligible'] = false;
                    $units[$unitId]['reasons'][] = 'Already registered for this unit';
                }
            }

            return ApiResponse::compatible([
                'success' => true,
                'data' => [
                    'semester' => $this->presenter->academicPeriod($activeSemester),
                    'units' => array_values($units),
                    'registered_units' => $registeredUnits,
                ],
            ]);
        } catch (NotFoundHttpException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            return ApiResponse::compatible(['success' => false, 'message' => $exception->getMessage()], 400);
        }
    }

    public function checkEligibility(CheckCourseRegistrationEligibilityRequest $request): JsonResponse
    {
        try {
            return ApiResponse::compatible([
                'success' => true,
                'data' => $this->eligibility->handle($request->integer('student_id'), $request->integer('course_offering_id'), $this->campusId()),
            ]);
        } catch (NotFoundHttpException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            return ApiResponse::compatible(['success' => false, 'message' => $exception->getMessage()], 400);
        }
    }

    public function getStudentRegistrations(ListStudentCourseRegistrationsRequest $request): JsonResponse
    {
        return ApiResponse::compatible([
            'success' => true,
            'data' => $this->studentRegistrations->handle(
                $request->integer('student_id'),
                $request->filled('semester_id') ? $request->integer('semester_id') : null,
                $this->campusId(),
            ),
        ]);
    }

    private function canEditRegistrationStatus(CourseRegistration $registration): bool
    {
        $courseOffering = $registration->courseOffering;
        $now = now()->toDateString();

        if (! $courseOffering->registration_start_date && ! $courseOffering->registration_end_date) {
            return true;
        }

        return (! $courseOffering->registration_start_date || $courseOffering->registration_start_date <= $now)
            && (! $courseOffering->registration_end_date || $courseOffering->registration_end_date >= $now);
    }

    private function campusId(): int
    {
        return (int) session('current_campus_id');
    }

    private function assertRegistrationInCampus(CourseRegistration $registration): void
    {
        abort_unless(CourseOffering::query()
            ->whereKey($registration->course_offering_id)
            ->where('campus_id', $this->campusId())
            ->exists(), 404);
    }
}
