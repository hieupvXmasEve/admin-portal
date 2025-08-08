<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campus\StoreCampusRequest;
use App\Http\Requests\Campus\UpdateCampusRequest;
use App\Http\Resources\Campus\CampusResource;
use App\Models\Campus;
use App\Services\CampusService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CampusController extends Controller
{
    public function __construct(protected CampusService $campusService) {}

    public function index(): AnonymousResourceCollection
    {
        return CampusResource::collection(Campus::paginate());
    }

    public function store(StoreCampusRequest $request): CampusResource
    {
        $campus = $this->campusService->createCampus($request->validated());

        return new CampusResource($campus);
    }

    public function show(Campus $campus): CampusResource
    {
        return new CampusResource($campus);
    }

    public function update(UpdateCampusRequest $request, Campus $campus): CampusResource
    {
        $updatedCampus = $this->campusService->updateCampus($campus, $request->validated());

        return new CampusResource($updatedCampus);
    }

    public function destroy(Campus $campus): \Illuminate\Http\Response
    {
        $this->campusService->deleteCampus($campus);

        return response()->noContent();
    }
}
