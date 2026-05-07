<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\RetryDngWebhookEventAction;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Modules\Finance\Queries\Dng\GetDngWebhookEventDetailsQuery;
use App\Modules\Finance\Queries\Dng\ListDngWebhookEventsQuery;
use Illuminate\Http\RedirectResponse;
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

    public function retry(DngWebhookEvent $dngWebhookEvent, RetryDngWebhookEventAction $action): RedirectResponse
    {
        try {
            $event = $action->run($dngWebhookEvent);
        } catch (\Throwable $e) {
            Inertia::flash('error', 'Retry failed: '.$e->getMessage());

            return back();
        }

        $message = match ($event->processing_status) {
            DngWebhookEvent::STATUS_PROCESSED => 'Event reprocessed successfully — payment created/updated.',
            DngWebhookEvent::STATUS_SKIPPED => 'Event skipped: '.($event->error_message ?? 'duplicate or already applied').'.',
            DngWebhookEvent::STATUS_MISMATCH => 'Event ended in mismatch: '.($event->error_message ?? 'data mismatch').'.',
            DngWebhookEvent::STATUS_FAILED_TERMINAL => 'Event failed permanently: '.($event->error_message ?? 'unknown error').'.',
            DngWebhookEvent::STATUS_FAILED_RETRYABLE => 'Event failed but is retryable: '.($event->error_message ?? 'transient error').'.',
            default => 'Retry finished with status: '.$event->processing_status,
        };

        $flashKey = $event->processing_status === DngWebhookEvent::STATUS_PROCESSED ? 'success' : 'info';
        Inertia::flash($flashKey, $message);

        return back();
    }
}
