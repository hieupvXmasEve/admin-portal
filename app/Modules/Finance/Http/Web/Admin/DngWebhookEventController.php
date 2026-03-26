<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Queries\Dng\GetDngWebhookEventDetailsQuery;
use App\Modules\Finance\Queries\Dng\ListDngWebhookEventsQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DngWebhookEventController extends Controller
{
    public function index(Request $request, ListDngWebhookEventsQuery $query): Response
    {
        $result = $query->handle($request);

        return Inertia::render('Finance/Payments/DngWebhookEvents/Index', [
            'items' => $result['items'],
            'stats' => $result['stats'],
            'filters' => $result['filters'],
        ]);
    }

    public function show(DngWebhookEvent $dngWebhookEvent, GetDngWebhookEventDetailsQuery $query): Response
    {
        return Inertia::render('Finance/Payments/DngWebhookEvents/Show', [
            'event' => $query->handle($dngWebhookEvent),
        ]);
    }
}
