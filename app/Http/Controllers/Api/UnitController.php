<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Http\Resources\Unit\UnitResource;
use App\Models\Unit;
use App\Services\UnitService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController extends Controller
{
    public function __construct(protected UnitService $unitService) {}

    public function index(): AnonymousResourceCollection
    {
        return UnitResource::collection(Unit::paginate());
    }

    public function store(StoreUnitRequest $request): UnitResource
    {
        $unit = $this->unitService->createUnit($request->validated());
        return new UnitResource($unit);
    }

    public function show(Unit $unit): UnitResource
    {
        return new UnitResource($unit);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): UnitResource
    {
        $updatedUnit = $this->unitService->updateUnit($unit, $request->validated());
        return new UnitResource($updatedUnit);
    }

    public function destroy(Unit $unit): \Illuminate\Http\Response
    {
        $this->unitService->deleteUnit($unit);
        return response()->noContent();
    }

}
