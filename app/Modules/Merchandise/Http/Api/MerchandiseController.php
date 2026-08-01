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
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MerchandiseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', Merchandise::STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $merchandise = Merchandise::query()
            ->with(['images', 'variants'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 15);

        return ApiResponse::paginated($merchandise);
    }

    public function show(Merchandise $merchandise): JsonResponse
    {
        $merchandise->load(['images', 'variants.campus']);

        return ApiResponse::success($merchandise);
    }

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
