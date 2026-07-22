<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web\Admin;

use App\Constants\CourseOfferingRoutes;
use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Actions\DeleteCourseOfferingAction;
use App\Modules\Academic\Delivery\Exceptions\CourseOfferingDeletionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

final class CourseOfferingDeletionController extends Controller
{
    public function __invoke(CourseOffering $courseOffering): RedirectResponse
    {
        if ((int) $courseOffering->campus_id !== (int) app('campus')->id) {
            abort(404);
        }

        try {
            $registrationCount = DeleteCourseOfferingAction::run([
                'course_offering_id' => (int) $courseOffering->id,
                'campus_id' => (int) app('campus')->id,
            ]);

            $message = 'Course offering deleted successfully.';
            if ($registrationCount > 0) {
                $message .= " {$registrationCount} associated registration(s) were also removed.";
            }

            Inertia::flash('success', $message);

            return Redirect::route(CourseOfferingRoutes::INDEX);
        } catch (CourseOfferingDeletionException $exception) {
            Inertia::flash('error', $exception->getMessage());

            return Redirect::back();
        } catch (\Throwable $exception) {
            Log::error('Failed to delete course offering.', [
                'course_offering_id' => $courseOffering->id,
                'exception' => $exception,
            ]);
            Inertia::flash('error', 'Failed to delete course offering: '.$exception->getMessage());

            return Redirect::back();
        }
    }
}
