<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Merchandise;
use App\Models\MerchandiseVariant;
use App\Modules\Merchandise\Actions\CreateMerchandiseVariantAction;
use App\Modules\Merchandise\Actions\UpdateMerchandiseVariantAction;
use App\Modules\Merchandise\Http\Requests\AdjustMerchandiseVariantStockRequest;
use App\Modules\Merchandise\Http\Requests\StoreMerchandiseVariantRequest;
use App\Modules\Merchandise\Http\Requests\UpdateMerchandiseVariantRequest;
use App\Modules\Merchandise\Support\StockService;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class MerchandiseVariantController extends Controller
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly CampusPermissionReader $permissionReader,
    ) {}

    public function store(StoreMerchandiseVariantRequest $request, Merchandise $merchandise): JsonResponse
    {
        $validated = $request->validated();

        // Campus-scope creation to the TARGET campus (RT-13): the route can:
        // gate is campus-blind, so a variant may only be created at a campus the
        // staff member actually holds the permission at. There is no record yet,
        // so the check resolves from the requested campus_id.
        $campusId = (int) $validated['campus_id'];
        $granted = $this->permissionReader->permissionCodesForUserId((int) Auth::id(), $campusId);
        abort_unless(in_array('manage_merchandise_variant', $granted, true), Response::HTTP_FORBIDDEN);

        $variant = CreateMerchandiseVariantAction::run($merchandise, $validated);

        return ApiResponse::success($variant, message: 'Variant created successfully', status: Response::HTTP_CREATED);
    }

    public function update(UpdateMerchandiseVariantRequest $request, MerchandiseVariant $variant): JsonResponse
    {
        $this->authorize('update', $variant);

        $variant = UpdateMerchandiseVariantAction::run($variant, $request->validated());

        return ApiResponse::success($variant, message: 'Variant updated successfully');
    }

    public function adjustStock(AdjustMerchandiseVariantStockRequest $request, MerchandiseVariant $variant): JsonResponse
    {
        $this->authorize('adjustStock', $variant);

        $validated = $request->validated();

        try {
            $movement = $this->stockService->adjustStock(
                $variant,
                (int) $validated['change'],
                $validated['type'],
                Auth::id(),
                $validated['note'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        }

        return ApiResponse::success($movement, message: 'Stock adjusted successfully');
    }

    public function stockMovements(MerchandiseVariant $variant): JsonResponse
    {
        $this->authorize('viewStockMovements', $variant);

        $movements = $variant->stockMovements()->orderBy('created_at', 'desc')->paginate(15);

        return ApiResponse::paginated($movements);
    }
}
