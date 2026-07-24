<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web\Admin;

use App\Constants\CourseOfferingRoutes;
use App\Http\Controllers\Controller;
use App\Modules\Academic\Delivery\Actions\BulkDeleteCourseOfferingsAction;
use App\Modules\Academic\Delivery\Exceptions\CourseOfferingDeletionException;
use App\Modules\Academic\Http\Requests\CourseDelivery\BulkDeleteCourseOfferingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

final class CourseOfferingBulkDeletionController extends Controller
{
    public function __invoke(BulkDeleteCourseOfferingsRequest $request): RedirectResponse
    {
        try {
            $registrationCount = BulkDeleteCourseOfferingsAction::run([
                'course_offering_ids' => array_map('intval', $request->validated('ids')),
                'campus_id' => (int) app('campus')->id,
            ]);
            $message = 'Selected course offerings deleted successfully.';
            if ($registrationCount > 0) {
                $message .= " {$registrationCount} associated registration(s) were also removed.";
            }
            Inertia::flash('success', $message);

            return Redirect::route(CourseOfferingRoutes::INDEX);
        } catch (CourseOfferingDeletionException $exception) {
            Inertia::flash('error', $exception->getMessage());

            return Redirect::back();
        } catch (\Throwable $exception) {
            Log::error('Failed to bulk delete course offerings.', ['exception' => $exception]);
            Inertia::flash('error', 'Failed to delete course offerings: '.$exception->getMessage());

            return Redirect::back();
        }
    }
}
