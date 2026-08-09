<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Student;
use App\Modules\Merchandise\Models\Merchandise;
use App\Modules\Merchandise\Queries\GetMerchandiseAvailabilityQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Store catalog reads, scoped to the authenticated student's own campus (D2).
 * Read-only — no permission gate beyond student.api.auth.
 */
class StudentMerchandiseController extends Controller
{
    public function __construct(private readonly GetMerchandiseAvailabilityQuery $availabilityQuery) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();
        $campusId = (int) $student->campus_id;

        $perPage = max(1, min((int) $request->input('per_page', 15), 50));

        $merchandise = Merchandise::query()
            ->whereIn('status', [Merchandise::STATUS_ACTIVE, Merchandise::STATUS_COMING_SOON])
            ->with(['images' => fn ($q) => $q->where('is_primary', true)->limit(1)])
            ->orderBy('name')
            ->paginate($perPage);

        $merchandise->getCollection()->transform(
            fn (Merchandise $item) => $this->presentSummary($item, $campusId)
        );

        return ApiResponse::paginated($merchandise);
    }

    public function show(Request $request, Merchandise $merchandise): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();
        $campusId = (int) $student->campus_id;

        if (! in_array($merchandise->status, [Merchandise::STATUS_ACTIVE, Merchandise::STATUS_COMING_SOON], true)) {
            return ApiResponse::notFound('Merchandise not found');
        }

        $merchandise->load([
            'images',
            'variants' => fn ($q) => $q->where('campus_id', $campusId)->where('is_active', true),
        ]);

        return ApiResponse::success([
            'id' => $merchandise->id,
            'name' => $merchandise->name,
            'description' => $merchandise->description,
            'gold_price' => (int) $merchandise->gold_price,
            'availability' => $this->availabilityQuery->handle($merchandise, $campusId),
            'images' => $merchandise->images->map(fn ($image) => [
                'path' => $image->path,
                'is_primary' => (bool) $image->is_primary,
            ])->values(),
            'variants' => $merchandise->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'color' => $variant->color,
                'size' => $variant->size,
                'stock_quantity' => (int) $variant->stock_quantity,
            ])->values(),
        ]);
    }

    /** @return array<string, mixed> */
    private function presentSummary(Merchandise $merchandise, int $campusId): array
    {
        return [
            'id' => $merchandise->id,
            'name' => $merchandise->name,
            'gold_price' => (int) $merchandise->gold_price,
            'availability' => $this->availabilityQuery->handle($merchandise, $campusId),
            'image' => optional($merchandise->images->first())->path,
        ];
    }
}
