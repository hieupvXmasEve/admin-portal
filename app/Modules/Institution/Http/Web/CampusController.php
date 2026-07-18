<?php

declare(strict_types=1);

namespace App\Modules\Institution\Http\Web;

use App\Http\Controllers\Controller;
use App\Modules\Institution\Actions\CreateCampusAction;
use App\Modules\Institution\Actions\UpdateCampusAction;
use App\Modules\Institution\Http\Requests\Campus\ListCampusRequest;
use App\Modules\Institution\Http\Requests\Campus\StoreCampusRequest;
use App\Modules\Institution\Http\Requests\Campus\UpdateCampusRequest;
use App\Modules\Institution\Queries\GetCampusQuery;
use App\Modules\Institution\Queries\ListCampusesQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CampusController extends Controller
{
    public function __construct(
        private readonly ListCampusesQuery $listCampuses,
        private readonly GetCampusQuery $campuses,
    ) {}

    public function index(ListCampusRequest $request): Response
    {
        return Inertia::render('Campuses/Index', [
            'campuses' => $this->listCampuses->handle($request->validated()),
            'filters' => $request->only(['search', 'sort', 'direction', 'per_page']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Campuses/Create');
    }

    public function store(StoreCampusRequest $request): RedirectResponse
    {
        CreateCampusAction::run($request->validated());

        Inertia::flash('message', 'Campus created successfully.');

        return back();
    }

    public function edit(int|string $campus): Response
    {
        return Inertia::render('Campuses/Edit', [
            'campus' => $this->campuses->forEdit($campus),
        ]);
    }

    public function update(UpdateCampusRequest $request, int|string $campus): RedirectResponse
    {
        UpdateCampusAction::run([
            ...$request->validated(),
            'campus_id' => (int) $campus,
        ]);

        Inertia::flash('message', 'Campus updated successfully.');

        return back();
    }
}
