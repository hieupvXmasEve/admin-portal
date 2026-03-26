<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\Dng\GetDngPaymentRequestDetailsQuery;
use App\Modules\Finance\Queries\Dng\ListDngPaymentRequestsQuery;
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
}
