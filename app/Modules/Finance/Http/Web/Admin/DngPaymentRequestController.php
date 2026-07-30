<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Campus;
use App\Modules\Finance\Actions\CancelDngPaymentRequestAction;
use App\Modules\Finance\Actions\ResolveDngReservationOutcomeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Http\Export\DngPaymentRequestExport;
use App\Modules\Finance\Http\Requests\Dng\ExportDngPaymentRequestsRequest;
use App\Modules\Finance\Http\Requests\Dng\ResolveDngReservationOutcomeRequest;
use App\Modules\Finance\Http\Requests\Student360\ReviewedCancelDngRequest;
use App\Modules\Finance\Queries\Dng\GetDngPaymentRequestDetailsQuery;
use App\Modules\Finance\Queries\Dng\ListDngPaymentRequestsQuery;
use App\Modules\Finance\Queries\Student360\BuildDngCancelImpactQuery;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class DngPaymentRequestController extends Controller
{
    public function __construct(private readonly StudentReferenceReader $studentReferences) {}

    public function index(Request $request, ListDngPaymentRequestsQuery $query): Response
    {
        $result = $query->handle($request);

        return Inertia::render('Finance/Payments/DngPaymentRequests/Index', [
            'items' => $result['items'],
            'stats' => $result['stats'],
            'filters' => $result['filters'],
        ]);
    }

    public function export(ExportDngPaymentRequestsRequest $request, StudentReferenceReader $studentReferences)
    {
        return Excel::download(
            new DngPaymentRequestExport($request->validated(), $studentReferences),
            'dng-payment-requests.xlsx',
        );
    }

    public function show(DngPaymentRequest $dngPaymentRequest, GetDngPaymentRequestDetailsQuery $query): Response
    {
        return Inertia::render('Finance/Payments/DngPaymentRequests/Show', [
            'request' => $query->handle($dngPaymentRequest),
        ]);
    }

    public function cancel(DngPaymentRequest $dngPaymentRequest, CancelDngPaymentRequestAction $action): RedirectResponse
    {
        $this->assertCampusAccess($dngPaymentRequest);

        $cancellableStatuses = [DngPaymentRequest::STATUS_PENDING, DngPaymentRequest::STATUS_PUSHED_TO_DNG];
        if (! in_array($dngPaymentRequest->status, $cancellableStatuses, true)) {
            Inertia::flash('error', 'This DNG payment request cannot be cancelled.');

            return back();
        }

        try {
            $action->run($dngPaymentRequest);
        } catch (\Throwable $e) {
            Inertia::flash('error', 'Failed to cancel DNG payment request: '.$e->getMessage());

            return back();
        }

        Inertia::flash('success', 'DNG payment request cancelled.');

        return back();
    }

    public function resolveOutcome(
        DngPaymentRequest $dngPaymentRequest,
        ResolveDngReservationOutcomeRequest $request,
        ResolveDngReservationOutcomeAction $action,
    ): RedirectResponse {
        $this->assertCampusAccess($dngPaymentRequest);

        try {
            $resolved = $action->handle($dngPaymentRequest, $request->validated('outcome'), [
                'reason' => $request->validated('reason'),
                'resolved_by_user_id' => $request->user()?->id,
            ]);
        } catch (\Throwable $e) {
            Inertia::flash('error', 'Failed to reconcile DNG reservation: '.$e->getMessage());

            return back();
        }

        Inertia::flash('success', "DNG reservation resolved as {$resolved->status}.");

        return back();
    }

    /** JSON: impact of cancelling this DNG (linked charges + blocking reasons). */
    public function cancelImpact(DngPaymentRequest $dngPaymentRequest, BuildDngCancelImpactQuery $query): JsonResponse
    {
        $this->assertCampusAccess($dngPaymentRequest);

        return ApiResponse::success($query->handle($dngPaymentRequest));
    }

    /** Reviewed collection cancel: reason + acknowledgement, never a charge void. */
    public function cancelReviewed(
        DngPaymentRequest $dngPaymentRequest,
        ReviewedCancelDngRequest $request,
        CancelDngPaymentRequestAction $action,
    ): RedirectResponse {
        $this->assertCampusAccess($dngPaymentRequest);

        $cancellable = [DngPaymentRequest::STATUS_PENDING, DngPaymentRequest::STATUS_PUSHED_TO_DNG];
        if (! in_array($dngPaymentRequest->status, $cancellable, true)) {
            Inertia::flash('error', 'This DNG payment request cannot be cancelled.');

            return back();
        }

        try {
            $action->run($dngPaymentRequest);
        } catch (\Throwable $e) {
            Inertia::flash('error', 'Failed to cancel DNG payment request: '.$e->getMessage());

            return back();
        }

        Inertia::flash('success', 'DNG collection cancellation completed. Underlying obligations remain active. Reason: '.$request->validated('reason'));

        return back();
    }

    private function assertCampusAccess(DngPaymentRequest $paymentRequest): void
    {
        $campus = app()->bound('campus') ? app('campus') : null;
        $student = $this->studentReferences->find((int) $paymentRequest->student_id);
        if ($campus instanceof Campus && $campus->id !== null && $student?->campusId !== (int) $campus->id) {
            throw new AuthorizationException;
        }
    }
}
