<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Http\Requests\Campus\ShowCampusRequest;
use App\Modules\Academic\Queries\GetCampusDetailQuery;
use Inertia\Inertia;
use Inertia\Response;

class CampusDetailController extends Controller
{
    public function __construct(
        private readonly GetCampusDetailQuery $details,
    ) {}

    public function show(ShowCampusRequest $request, int|string $campus): Response
    {
        $detail = $this->details->handle($campus, $request->validated());

        return Inertia::render('Campuses/Show', [
            'campus' => $detail['campus'],
            'buildings' => $detail['buildings'],
            'filters' => $request->only(['search', 'sort', 'direction', 'per_page']),
        ]);
    }
}
