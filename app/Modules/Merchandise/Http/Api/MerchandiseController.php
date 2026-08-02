<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Merchandise;
use App\Modules\Merchandise\Actions\ArchiveMerchandiseAction;
use App\Modules\Merchandise\Actions\CreateMerchandiseAction;
use App\Modules\Merchandise\Actions\UpdateMerchandiseAction;
use App\Modules\Merchandise\Http\Requests\StoreMerchandiseRequest;
use App\Modules\Merchandise\Http\Requests\UpdateMerchandiseRequest;
use App\Modules\Merchandise\Support\MerchandiseCatalogNotificationPublisher;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Write-only JSON API (create/update/archive). Reads are server-rendered via
 * Inertia — see Http/Web/MerchandiseWebController for index/show/create/edit.
 */
class MerchandiseController extends Controller
{
    public function __construct(private readonly MerchandiseCatalogNotificationPublisher $notifications) {}

    public function store(StoreMerchandiseRequest $request): JsonResponse
    {
        $merchandise = CreateMerchandiseAction::run($request->validated());

        if ($merchandise->status === Merchandise::STATUS_ACTIVE) {
            $this->notifications->catalogItemPublished($merchandise);
        }

        return ApiResponse::success($merchandise, message: 'Merchandise created successfully', status: Response::HTTP_CREATED);
    }

    public function update(UpdateMerchandiseRequest $request, Merchandise $merchandise): JsonResponse
    {
        $wasActive = $merchandise->status === Merchandise::STATUS_ACTIVE;

        $merchandise = UpdateMerchandiseAction::run($merchandise, $request->validated());

        // Notify on the coming_soon/hidden -> active TRANSITION only — not
        // on every subsequent edit while it's already active (that would
        // re-broadcast to every student on a price typo fix).
        if (! $wasActive && $merchandise->status === Merchandise::STATUS_ACTIVE) {
            $this->notifications->catalogItemPublished($merchandise);
        }

        return ApiResponse::success($merchandise, message: 'Merchandise updated successfully');
    }

    public function archive(Merchandise $merchandise): JsonResponse
    {
        $merchandise = ArchiveMerchandiseAction::run($merchandise);

        return ApiResponse::success($merchandise, message: 'Merchandise archived successfully');
    }
}
