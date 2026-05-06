<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Modules\Finance\Actions\CancelDngPaymentRequestAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\Dng\GetDngPaymentRequestDetailsQuery;
use App\Modules\Finance\Queries\Dng\ListDngPaymentRequestsQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DngPaymentRequestController extends Controller
{
    public function index(Request $request, ListDngPaymentRequestsQuery $query): Response
    {
        $result = $query->handle($request);

        return Inertia::render('Finance/Payments/DngPaymentRequests/Index', [
            'items' => $result['items'],
            'stats' => $result['stats'],
            'filters' => $result['filters'],
        ]);
    }

    public function show(DngPaymentRequest $dngPaymentRequest, GetDngPaymentRequestDetailsQuery $query): Response
    {
        return Inertia::render('Finance/Payments/DngPaymentRequests/Show', [
            'request' => $query->handle($dngPaymentRequest),
        ]);
    }

    public function cancel(DngPaymentRequest $dngPaymentRequest, CancelDngPaymentRequestAction $action): RedirectResponse
    {
        $dngPaymentRequest->loadMissing('student:id,campus_id');
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

    private function assertCampusAccess(DngPaymentRequest $paymentRequest): void
    {
        $campus = app()->bound('campus') ? app('campus') : null;
        if ($campus instanceof Campus && $campus->id !== null && $paymentRequest->student?->campus_id !== (int) $campus->id) {
            throw new AuthorizationException;
        }
    }
}
