<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Actions\FinalizeCourseOfferingAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cockpit Finalize action (ADR 0013): completes a course offering from the
 * course-offering detail page. The frontend follows the redirect with an
 * Inertia partial reload of `operational_state`, so blockers and success both
 * surface through the same read-model contract plus a flash toast.
 */
class FinalizeCourseOfferingController extends Controller
{
    public function __invoke(CourseOffering $courseOffering): RedirectResponse
    {
        try {
            $result = FinalizeCourseOfferingAction::run(['course_offering_id' => $courseOffering->id]);

            Inertia::flash('message', "Course '{$result['course_code']}' finalized successfully.");
        } catch (RuntimeException $e) {
            // The action signals a cross-campus offering with a 404 exception
            // code; everything else is a business-rule block shown as a toast.
            if ($e->getCode() === Response::HTTP_NOT_FOUND) {
                abort(Response::HTTP_NOT_FOUND);
            }

            Inertia::flash('error', $e->getMessage());
        }

        return Redirect::back();
    }
}
