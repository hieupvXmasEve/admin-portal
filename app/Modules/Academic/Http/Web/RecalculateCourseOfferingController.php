<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Modules\Academic\Actions\MarkCourseOfferingCompletedAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cockpit Recalculate action (ADR 0013): re-runs completion on an
 * already-completed course offering from the cockpit. Mirrors
 * FinalizeCourseOfferingController's redirect + partial-reload contract;
 * gated by the stricter recalculate_course_offering permission.
 */
class RecalculateCourseOfferingController extends Controller
{
    public function __invoke(CourseOffering $courseOffering): RedirectResponse
    {
        try {
            $result = MarkCourseOfferingCompletedAction::run($courseOffering, recalculate: true);

            Inertia::flash('message', "Course '{$result['course_code']}' recalculated successfully.");
        } catch (RuntimeException $e) {
            if ($e->getCode() === Response::HTTP_NOT_FOUND) {
                abort(Response::HTTP_NOT_FOUND);
            }

            Inertia::flash('error', $e->getMessage());
        }

        return Redirect::back();
    }
}
