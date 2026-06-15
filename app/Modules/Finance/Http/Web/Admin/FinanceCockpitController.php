<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Finance\Queries\Cockpit\GetFinanceCockpitDataHealthQuery;
use App\Modules\Finance\Queries\Cockpit\GetFinanceCockpitOverviewQuery;
use App\Modules\Finance\Queries\Cockpit\GetFinanceCockpitQueueRowsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceCockpitController extends Controller
{
    public function index(
        Request $request,
        GetFinanceCockpitOverviewQuery $overview,
        GetFinanceCockpitDataHealthQuery $dataHealth,
    ): Response {
        $semesterId = $this->selectedSemesterId();
        $phaseOverride = $request->session()->get('finance_cockpit_phase');

        $props = $overview->handle($semesterId, $phaseOverride);

        $props['data_health'] = Inertia::defer(fn () => $dataHealth->handle(
            $this->currentCampusId(),
            $request->user()?->can('view_finance_all_campus') ?? false,
        ));

        return Inertia::render('Finance/Cockpit/Index', $props);
    }

    public function queueRows(string $queue, GetFinanceCockpitQueueRowsQuery $query): JsonResponse
    {
        return ApiResponse::success([
            'queue' => $queue,
            'rows' => $query->handle($queue, $this->selectedSemesterId()),
        ]);
    }

    public function setPhase(Request $request): RedirectResponse
    {
        $validated = $request->validate(['phase' => ['nullable', 'in:early,mid,late']]);
        $request->session()->put('finance_cockpit_phase', $validated['phase'] ?? null);

        return back();
    }

    private function selectedSemesterId(): ?int
    {
        $id = session('current_semester_id');

        return $id !== null ? (int) $id : null;
    }

    private function currentCampusId(): ?int
    {
        if (! app()->bound('campus')) {
            return null;
        }
        $campus = app('campus');

        return $campus?->id !== null ? (int) $campus->id : null;
    }
}