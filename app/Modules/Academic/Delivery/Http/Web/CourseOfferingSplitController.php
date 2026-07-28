<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web;

use App\Constants\CourseOfferingRoutes;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Actions\SplitCourseOfferingAction;
use App\Modules\Academic\Delivery\Exceptions\CourseOfferingSplitException;
use App\Modules\Academic\Delivery\Exceptions\InstructorAssignmentException;
use App\Modules\Academic\Delivery\Http\Requests\CourseDelivery\SplitCourseOfferingRequest;
use App\Shared\Contracts\Identity\AvailableLecturerReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CourseOfferingSplitController extends Controller
{
    public function show(CourseOffering $courseOffering, AvailableLecturerReader $lecturers): Response|RedirectResponse
    {
        if ((int) $courseOffering->campus_id !== (int) app('campus')->id) {
            abort(404);
        }

        if ($courseOffering->section_code) {
            return Redirect::back()
                ->with('error', 'This course offering is already a section and cannot be split further.');
        }

        if ($courseOffering->current_enrollment === 0) {
            return Redirect::back()
                ->with('error', 'Cannot split a course offering with no enrolled students.');
        }

        $courseOffering->load([
            'semester',
            'unit',
            'lecture',
            'courseRegistrations.student',
        ]);

        $enrolledStudents = $courseOffering->courseRegistrations
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->map(function ($registration): array {
                return [
                    'id' => $registration->student->id,
                    'student_id' => $registration->student->student_id,
                    'full_name' => $registration->student->full_name,
                    'email' => $registration->student->email,
                    'registration_id' => $registration->id,
                ];
            })
            ->values();

        return Inertia::render('CourseOfferings/Split', [
            'courseOffering' => $courseOffering,
            'enrolledStudents' => $enrolledStudents,
            'lectures' => $lecturers->all(),
        ]);
    }

    public function perform(SplitCourseOfferingRequest $request, CourseOffering $courseOffering): RedirectResponse
    {
        try {
            $sectionCount = SplitCourseOfferingAction::run([
                'course_offering_id' => (int) $courseOffering->id,
                'campus_id' => (int) app('campus')->id,
                'sections' => $request->validated('sections'),
            ]);

            return Redirect::route(CourseOfferingRoutes::INDEX)
                ->with('success', 'Course offering successfully split into '.$sectionCount.' sections. The original course offering has been deleted.');
        } catch (InstructorAssignmentException $exception) {
            throw ValidationException::withMessages([
                $exception->field => [$exception->getMessage()],
            ]);
        } catch (CourseOfferingSplitException $exception) {
            return Redirect::back()->with('error', $exception->getMessage());
        } catch (\Exception $exception) {
            return Redirect::back()
                ->with('error', 'Failed to split course offering: '.$exception->getMessage());
        }
    }
}
