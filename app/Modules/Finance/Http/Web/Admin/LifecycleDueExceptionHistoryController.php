<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\Operations\BackfillLifecycleDueExceptionReviewEventsAction;
use App\Modules\Finance\Http\Requests\Operations\ViewLifecycleDueExceptionHistoryRequest;
use App\Modules\Finance\Queries\Operations\GetLifecycleDueExceptionHistorySummaryQuery;
use App\Modules\Finance\Queries\Operations\ListLifecycleDueExceptionHistoryQuery;
use Inertia\Inertia;
use Inertia\Response;

class LifecycleDueExceptionHistoryController extends Controller
{
    public function index(
        ViewLifecycleDueExceptionHistoryRequest $request,
        BackfillLifecycleDueExceptionReviewEventsAction $backfillAction,
        ListLifecycleDueExceptionHistoryQuery $listQuery,
        GetLifecycleDueExceptionHistorySummaryQuery $summaryQuery,
    ): Response {
        $backfillAction->run();

        $validated = array_replace([
            'dng_payment_request_id' => null,
            'dng_item_id' => null,
            'student_id' => null,
            'search' => '',
            'event_type' => null,
            'review_status' => null,
            'exception_reason' => null,
            'performed_by_user_id' => null,
            'performed_from' => null,
            'performed_to' => null,
            'per_page' => 20,
            'sort' => 'performed_at',
            'direction' => 'desc',
        ], $request->validated());

        return Inertia::render('Finance/Operations/LifecycleExceptionHistory', [
            'history_events' => $listQuery->handle($validated),
            'summary' => $summaryQuery->handle($validated),
            'filters' => $validated,
            'filter_options' => $listQuery->filterOptions(),
            'selected_timeline' => $listQuery->selectedTimeline($validated),
            'links' => [
                'lifecycle_exceptions' => route('finance.operations.lifecycle-exceptions'),
            ],
        ]);
    }
}
