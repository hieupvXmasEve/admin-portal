<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Merchandise;
use App\Models\MerchandiseImage;
use App\Modules\Merchandise\Actions\AttachMerchandiseImageAction;
use App\Modules\Merchandise\Actions\DetachMerchandiseImageAction;
use App\Modules\Merchandise\Actions\ReorderMerchandiseImagesAction;
use App\Modules\Merchandise\Actions\SetPrimaryMerchandiseImageAction;
use App\Modules\Merchandise\Http\Requests\AttachMerchandiseImageRequest;
use App\Modules\Merchandise\Http\Requests\ReorderMerchandiseImagesRequest;
use App\Shared\Contracts\Upload\FileUploadGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MerchandiseImageController extends Controller
{
    // Cross-context uploads go through the Shared Contract, never the Upload
    // module's concrete classes (DomainBoundaryArchitectureTest). The gateway
    // runs the merchandise_image context's signature/polyglot validation.
    public function __construct(private readonly FileUploadGateway $uploads) {}

    public function store(AttachMerchandiseImageRequest $request, Merchandise $merchandise): JsonResponse
    {
        $validated = $request->validated();

        $stored = $this->uploads->store(
            $request->file('image'),
            'merchandise_image',
            Auth::id(),
        );

        // ponytail: store the resolved public URL in `path` for display; keep
        // the upload id out of scope until image lifecycle (delete/replace by
        // id) is actually needed.
        $path = $this->uploads->urlFor($stored->id);

        $image = AttachMerchandiseImageAction::run($merchandise, $path, [
            'sort_order' => $validated['sort_order'] ?? null,
            'is_primary' => $validated['is_primary'] ?? false,
        ]);

        return ApiResponse::success($image, message: 'Image attached successfully', status: Response::HTTP_CREATED);
    }

    public function destroy(MerchandiseImage $merchandiseImage): JsonResponse
    {
        DetachMerchandiseImageAction::run($merchandiseImage);

        return ApiResponse::success(null, message: 'Image detached successfully');
    }

    public function reorder(ReorderMerchandiseImagesRequest $request, Merchandise $merchandise): JsonResponse
    {
        $images = ReorderMerchandiseImagesAction::run($merchandise, $request->validated()['order']);

        return ApiResponse::success($images, message: 'Images reordered successfully');
    }

    public function setPrimary(MerchandiseImage $merchandiseImage): JsonResponse
    {
        $image = SetPrimaryMerchandiseImageAction::run($merchandiseImage);

        return ApiResponse::success($image, message: 'Primary image set successfully');
    }
}
