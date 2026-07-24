<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web\Admin;

use App\Constants\CourseOfferingRoutes;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Actions\DuplicateCourseOfferingAction;
use App\Modules\Academic\Delivery\Exceptions\CourseOfferingDuplicationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

final class CourseOfferingDuplicationController extends Controller
{
    public function __invoke(CourseOffering $courseOffering): RedirectResponse
    {
        if ((int) $courseOffering->campus_id !== (int) app('campus')->id) {
            abort(404);
        }

        try {
            DuplicateCourseOfferingAction::run([
                'course_offering_id' => (int) $courseOffering->id,
                'campus_id' => (int) app('campus')->id,
            ]);

            Inertia::flash('success', 'Course offering duplicated successfully. Please assign an instructor.');

            return Redirect::route(CourseOfferingRoutes::INDEX);
        } catch (CourseOfferingDuplicationException $exception) {
            Inertia::flash('error', $exception->getMessage());

            return Redirect::back();
        } catch (\Throwable $exception) {
            Log::error('Failed to duplicate course offering.', [
                'course_offering_id' => $courseOffering->id,
                'exception' => $exception,
            ]);
            Inertia::flash('error', 'Failed to duplicate course offering: '.$exception->getMessage());

            return Redirect::back();
        }
    }
}
