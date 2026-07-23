<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

use App\Constants\CurriculumRoutes;
use App\Http\Requests\DuplicateCurriculumVersionRequest;
use App\Http\Requests\StoreCurriculumVersionRequest;
use App\Http\Requests\UpdateCurriculumVersionRequest;
use App\Models\CurriculumVersion;
use App\Modules\Academic\Catalog\Actions\CreateCurriculumVersionAction;
use App\Modules\Academic\Catalog\Actions\ModifyCurriculumVersionAction;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Catalog owns mutations while summary projections remain a compatibility
 * boundary during their staged query extraction.
 */
class CurriculumVersionController extends \App\Http\Controllers\Web\CurriculumVersionController
{
    public function store(StoreCurriculumVersionRequest $request): RedirectResponse
    {
        $curriculumVersion = app(CreateCurriculumVersionAction::class)->handle($request->validated());
        Inertia::flash('success', 'Curriculum version created successfully');

        return redirect()->route(CurriculumRoutes::VERSION_SUMMARY_OVERVIEW, $curriculumVersion);
    }

    public function update(
        UpdateCurriculumVersionRequest $request,
        CurriculumVersion $curriculumVersion,
    ): RedirectResponse {
        try {
            app(ModifyCurriculumVersionAction::class)->update($curriculumVersion, $request->validated());
        } catch (\DomainException $exception) {
            return redirect()->route(CurriculumRoutes::VERSION_INDEX)->withErrors(['error' => $exception->getMessage()]);
        }

        Inertia::flash('success', 'Curriculum version updated successfully');

        return redirect()->route(CurriculumRoutes::VERSION_INDEX, $curriculumVersion);
    }

    public function destroy(CurriculumVersion $curriculumVersion): RedirectResponse
    {
        try {
            app(ModifyCurriculumVersionAction::class)->delete($curriculumVersion);
        } catch (\DomainException $exception) {
            return redirect()->route(CurriculumRoutes::VERSION_INDEX)->withErrors(['error' => $exception->getMessage()]);
        }

        Inertia::flash('success', 'Curriculum version deleted successfully');

        return redirect()->route(CurriculumRoutes::VERSION_INDEX);
    }

    public function duplicate(
        DuplicateCurriculumVersionRequest $request,
        CurriculumVersion $curriculumVersion,
    ): RedirectResponse {
        $duplicatedVersion = app(ModifyCurriculumVersionAction::class)->duplicate($curriculumVersion, $request->validated());
        Inertia::flash('success', "Curriculum version duplicated successfully as '{$duplicatedVersion->version_code}'");

        return redirect()->route(CurriculumRoutes::VERSION_SUMMARY_OVERVIEW, $duplicatedVersion);
    }
}
