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
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Write-only JSON API (create/update/archive). Reads are server-rendered via
 * Inertia — see Http/Web/MerchandiseWebController for index/show/create/edit.
 */
class MerchandiseController extends Controller
{
    public function store(StoreMerchandiseRequest $request): JsonResponse
    {
        $merchandise = CreateMerchandiseAction::run($request->validated());

        return ApiResponse::success($merchandise, message: 'Merchandise created successfully', status: Response::HTTP_CREATED);
    }

    public function update(UpdateMerchandiseRequest $request, Merchandise $merchandise): JsonResponse
    {
        $merchandise = UpdateMerchandiseAction::run($merchandise, $request->validated());

        return ApiResponse::success($merchandise, message: 'Merchandise updated successfully');
    }

    public function archive(Merchandise $merchandise): JsonResponse
    {
        $merchandise = ArchiveMerchandiseAction::run($merchandise);

        return ApiResponse::success($merchandise, message: 'Merchandise archived successfully');
    }
}
