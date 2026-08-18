<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web\Admin;

use App\Constants\CourseOfferingRoutes;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Actions\DuplicateCourseOfferingAction;
use App\Modules\Academic\Delivery\Exceptions\CourseOfferingDuplicationException;
use App\Modules\Academic\Delivery\Http\Requests\CourseDelivery\DuplicateCourseOfferingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class CourseOfferingDuplicationController extends Controller
{
    public function __invoke(DuplicateCourseOfferingRequest $request, CourseOffering $courseOffering): RedirectResponse
    {
        if ((int) $courseOffering->campus_id !== (int) app('campus')->id) {
            abort(404);
        }

        try {
            DuplicateCourseOfferingAction::run([
                'course_offering_id' => (int) $courseOffering->id,
                'campus_id' => (int) app('campus')->id,
                'section_code' => $request->validated('section_code'),
            ]);

            Inertia::flash('success', 'Course offering duplicated successfully. Please assign an instructor.');

            return Redirect::route(CourseOfferingRoutes::INDEX);
        } catch (CourseOfferingDuplicationException $exception) {
            throw ValidationException::withMessages(['section_code' => [$exception->getMessage()]]);
        } catch (\Throwable $exception) {
            Log::error('Failed to duplicate course offering.', [
                'course_offering_id' => $courseOffering->id,
                'exception' => $exception,
            ]);

            throw ValidationException::withMessages([
                'section_code' => ['Failed to duplicate course offering. Please try again.'],
            ]);
        }
    }
}
