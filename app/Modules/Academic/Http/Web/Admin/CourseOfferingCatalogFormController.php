<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web\Admin;

use App\Constants\CourseOfferingRoutes;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Modules\Academic\Actions\CreateCourseOfferingAction;
use App\Modules\Academic\Actions\UpdateCourseOfferingAction;
use App\Modules\Academic\Delivery\Exceptions\InstructorAssignmentException;
use App\Modules\Academic\Http\Requests\CourseDelivery\StoreCourseOfferingRequest;
use App\Modules\Academic\Http\Requests\CourseDelivery\UpdateCourseOfferingRequest;
use App\Modules\Academic\Queries\GetCourseOfferingCatalogFormQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CourseOfferingCatalogFormController extends Controller
{
    public function create(GetCourseOfferingCatalogFormQuery $query): Response
    {
        $form = $query->handle();

        if ($form['active_semester'] === null) {
            return Inertia::render('course-offerings/Create', [
                'active_semester' => null,
                'units' => [],
                'lectures' => [],
                'syllabus_templates' => [],
                'error' => 'No semester is currently active. Please activate a semester before creating course offerings.',
            ]);
        }

        return Inertia::render('course-offerings/Create', [
            ...$form,
            'error' => null,
        ]);
    }

    public function store(StoreCourseOfferingRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $attributes['campus_id'] = app('campus')->id;

        try {
            CreateCourseOfferingAction::run($attributes);
        } catch (InstructorAssignmentException $exception) {
            throw ValidationException::withMessages([
                $exception->field => [$exception->getMessage()],
            ]);
        }

        Inertia::flash('success', 'Course offering created successfully.');

        return Redirect::route(CourseOfferingRoutes::INDEX);
    }

    public function edit(CourseOffering $courseOffering, GetCourseOfferingCatalogFormQuery $query): Response|RedirectResponse
    {
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        if (! $courseOffering->canModify()) {
            Inertia::flash('error', 'Cannot edit a course that is completed or cancelled.');

            return Redirect::back();
        }

        $form = $query->handle(
            $courseOffering->semester_id,
            $courseOffering->unit_id,
            $courseOffering->syllabus_template_id,
        );

        return Inertia::render('course-offerings/Edit', [
            'course_offering' => $this->courseOfferingPayload($courseOffering, $form),
            'lectures' => $form['lectures'],
            'syllabus_templates' => $form['syllabus_templates'],
        ]);
    }

    public function update(UpdateCourseOfferingRequest $request, CourseOffering $courseOffering): RedirectResponse
    {
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        if (! $courseOffering->canModify()) {
            Inertia::flash('error', 'Cannot update a course that is completed or cancelled.');

            return Redirect::back();
        }

        try {
            UpdateCourseOfferingAction::run($courseOffering, $request->validated());
        } catch (InstructorAssignmentException $exception) {
            throw ValidationException::withMessages([
                $exception->field => [$exception->getMessage()],
            ]);
        }

        Inertia::flash('success', 'Course offering updated successfully.');

        return Redirect::route(CourseOfferingRoutes::INDEX);
    }

    /**
     * @param  array{semester: array{id: int, name: string, code: string, start_date: mixed, end_date: mixed}|null, unit: array{id: int, code: string, name: string, credit_points: string}|null, syllabus_template: array{id: int, title: string|null, version: string|null, description: string|null}|null}  $catalog
     * @return array<string, mixed>
     */
    private function courseOfferingPayload(CourseOffering $courseOffering, array $catalog): array
    {
        return [
            'id' => $courseOffering->id,
            'semester_id' => $courseOffering->semester_id,
            'unit_id' => $courseOffering->unit_id,
            'syllabus_template_id' => $courseOffering->syllabus_template_id,
            'lecture_id' => $courseOffering->lecture_id,
            'section_code' => $courseOffering->section_code,
            'max_capacity' => $courseOffering->max_capacity,
            'waitlist_capacity' => $courseOffering->waitlist_capacity,
            'delivery_mode' => $courseOffering->delivery_mode,
            'schedule_days' => $courseOffering->schedule_days,
            'schedule_time_start' => $courseOffering->schedule_time_start,
            'schedule_time_end' => $courseOffering->schedule_time_end,
            'location' => $courseOffering->location,
            'enrollment_status' => $courseOffering->enrollment_status,
            'registration_start_date' => $courseOffering->registration_start_date,
            'registration_end_date' => $courseOffering->registration_end_date,
            'special_requirements' => $courseOffering->special_requirements,
            'notes' => $courseOffering->notes,
            'semester' => $catalog['semester'],
            'unit' => $catalog['unit'],
            'syllabus_template' => $catalog['syllabus_template'],
        ];
    }
}
