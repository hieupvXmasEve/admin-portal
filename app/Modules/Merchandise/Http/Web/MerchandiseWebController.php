<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Merchandise;
use App\Models\MerchandiseVariant;
use App\Models\StockMovement;
use App\Modules\Merchandise\Queries\GetMerchandiseAvailabilityQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin Inertia pages for the Merchandise module. Reads only — writes go
 * through the JSON API controllers in Http/Api (ApiResponse envelope),
 * called from the Vue pages via fetch() + router.reload().
 */
class MerchandiseWebController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'per_page']);
        $perPage = max(1, min((int) ($filters['per_page'] ?? 15), 100));

        $merchandise = Merchandise::query()
            ->withCount('variants')
            ->with(['images' => fn ($query) => $query->where('is_primary', true)->limit(1)])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        // Restructure to match the DataPagination component's expected shape
        // (mirrors app/Modules/Engagement/Http/Web/EventController::index).
        $paginationData = [
            'data' => $merchandise->getCollection()->map(fn (Merchandise $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'status' => $item->status,
                'gold_price' => $item->gold_price,
                'variants_count' => $item->variants_count,
                'primary_image' => $item->images->first()?->path,
            ])->all(),
            'links' => $merchandise->linkCollection()->toArray(),
            'current_page' => $merchandise->currentPage(),
            'last_page' => $merchandise->lastPage(),
            'per_page' => $merchandise->perPage(),
            'total' => $merchandise->total(),
            'from' => $merchandise->firstItem(),
            'to' => $merchandise->lastItem(),
            'prev_page_url' => $merchandise->previousPageUrl(),
            'next_page_url' => $merchandise->nextPageUrl(),
        ];

        return Inertia::render('Merchandise/Index', [
            'merchandise' => $paginationData,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Merchandise/Form', [
            'merchandise' => null,
            'isEditing' => false,
            'campuses' => Campus::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => Merchandise::STATUSES,
            'movementTypes' => StockMovement::TYPES,
        ]);
    }

    public function edit(Merchandise $merchandise): Response
    {
        $merchandise->load(['images', 'variants.campus']);

        return Inertia::render('Merchandise/Form', [
            'merchandise' => $merchandise,
            'isEditing' => true,
            'campuses' => Campus::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => Merchandise::STATUSES,
            'movementTypes' => StockMovement::TYPES,
        ]);
    }

    public function show(Merchandise $merchandise, GetMerchandiseAvailabilityQuery $availabilityQuery): Response
    {
        $merchandise->load(['images', 'variants.campus']);

        // ponytail: eager-loading a hasMany with ->limit() caps the WHOLE
        // result set across all parents, not per parent — a light per-variant
        // query keeps "recent N per variant" correct. Fine at this scale
        // (variants of a single merchandise item); switch to a ranked
        // subquery if the movement history grows large enough to matter.
        $merchandise->variants->each(function (MerchandiseVariant $variant) {
            $variant->setRelation(
                'recentStockMovements',
                $variant->stockMovements()->with('performedBy:id,name')->orderBy('created_at', 'desc')->limit(5)->get()
            );
        });

        $availabilityByCampus = $merchandise->variants
            ->pluck('campus_id')
            ->unique()
            ->mapWithKeys(fn (int $campusId) => [$campusId => $availabilityQuery->handle($merchandise, $campusId)]);

        return Inertia::render('Merchandise/Show', [
            'merchandise' => $merchandise,
            'availabilityByCampus' => $availabilityByCampus,
            'movementTypes' => StockMovement::TYPES,
        ]);
    }
}
