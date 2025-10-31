<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModuleProgressResource;
use App\Services\ModuleProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ModuleProgressController extends Controller
{
    public function __construct(
        private readonly ModuleProgressService $moduleProgressService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $student = $request->user();

        if (! $student) {
            abort(401, 'Unauthorized');
        }

        $progress = $this->moduleProgressService->getStudentModuleProgress($student);

        return ModuleProgressResource::collection($progress);
    }
}
