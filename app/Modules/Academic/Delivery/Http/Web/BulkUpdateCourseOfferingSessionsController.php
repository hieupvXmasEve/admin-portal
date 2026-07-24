<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Actions\BulkUpdateCourseOfferingSessionsAction;
use App\Modules\Academic\Http\Requests\Delivery\BulkUpdateCourseOfferingSessionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BulkUpdateCourseOfferingSessionsController extends Controller
{
    public function __invoke(BulkUpdateCourseOfferingSessionsRequest $request, CourseOffering $courseOffering): JsonResponse|RedirectResponse
    {
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        try {
            $validated = $request->validated();
            $count = BulkUpdateCourseOfferingSessionsAction::run(
                $courseOffering,
                $validated['session_ids'],
                $validated,
            );

            if ($request->expectsJson()) {
                return ApiResponse::success(['updated_count' => $count], [], "Successfully updated {$count} class session(s).");
            }

            Inertia::flash('message', "Successfully updated {$count} class session(s).");

            return redirect()->back();
        } catch (\DomainException $exception) {
            return ApiResponse::error($exception->getMessage(), [], 422);
        } catch (\Throwable $exception) {
            Log::error('Failed to bulk update class sessions: '.$exception->getMessage());

            if ($request->expectsJson()) {
                return ApiResponse::error('Failed to update class sessions: '.$exception->getMessage(), [], 500);
            }

            Inertia::flash('error', 'Failed to update class sessions: '.$exception->getMessage());

            return redirect()->back();
        }
    }
}
