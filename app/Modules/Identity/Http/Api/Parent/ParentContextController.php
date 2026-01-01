<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Api\Parent;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Queries\GetParentContextQuery;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ParentContextController extends Controller
{
    public function __construct(
        protected GetParentContextQuery $query
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ensure the user has a parent profile
        $parentProfile = $user->parentProfile;

        if (!$parentProfile) {
            return ApiResponse::error('Parent profile not found', [], 404);
        }

        $context = $this->query->handle($parentProfile);

        return ApiResponse::success($context);
    }
}
