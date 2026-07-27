<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web\Canvas;

use App\Http\Controllers\Controller;
use App\Models\CanvasIntegration;
use App\Modules\Academic\Delivery\Support\Canvas\CanvasSyncService;
use Illuminate\Http\RedirectResponse;

class CanvasSyncController extends Controller
{
    public function __construct(
        private CanvasSyncService $syncService
    ) {}

    public function syncCourses(CanvasIntegration $integration): RedirectResponse
    {
        // Check if integration is usable (has valid token OR can refresh)
        if (! $integration->isUsable()) {
            return back()->withErrors(['error' => 'Please authorize Canvas integration first']);
        }

        if ($integration->sync_status === 'syncing') {
            return back()->withErrors(['error' => 'Sync already in progress']);
        }

        try {
            $stats = $this->syncService->syncCoursesFromCanvas($integration);

            return back()->with('success', sprintf(
                'Sync completed: %d total, %d new, %d updated, %d skipped',
                $stats['total'],
                $stats['synced'],
                $stats['updated'],
                $stats['skipped']
            ));
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Sync failed: '.$e->getMessage()]);
        }
    }
}
