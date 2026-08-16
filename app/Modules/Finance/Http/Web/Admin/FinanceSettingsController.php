<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Modules\Finance\Http\Requests\Settings\UpdateFinanceSettingsRequest;
use App\Modules\Finance\Models\FinanceSetting;
use App\Modules\Finance\Queries\Dng\GetDngCampusMappingConfigurationQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceSettingsController extends Controller
{
    public function __construct(private readonly GetDngCampusMappingConfigurationQuery $dngCampusMappingConfiguration) {}

    public function show(Request $request): Response
    {
        $canViewSettings = $request->user()?->can('view_finance_settings') ?? false;
        $canViewDngCampusMapping = $request->user()?->can('view_finance_dng_campus_mappings') ?? false;

        abort_unless($canViewSettings || $canViewDngCampusMapping, 403);

        $settings = FinanceSetting::current();
        $campus = $this->currentCampus();

        return Inertia::render('Finance/Settings/Show', [
            'settings' => $canViewSettings ? [
                'credit_offset_enabled' => $settings->credit_offset_enabled,
                'credit_offset_min_balance' => (float) $settings->credit_offset_min_balance,
                'updated_at' => $settings->updated_at?->toIso8601String(),
            ] : null,
            'can_manage_settings' => $request->user()?->can('manage_finance_settings') ?? false,
            'dng_campus_mapping' => $canViewDngCampusMapping && $campus !== null
                ? $this->dngCampusMappingConfiguration->handle($campus)
                : null,
            'can_manage_dng_campus_mapping' => $request->user()?->can('manage_finance_dng_campus_mappings') ?? false,
        ]);
    }

    private function currentCampus(): ?Campus
    {
        $campus = app()->bound('campus') ? app('campus') : null;

        return $campus instanceof Campus && $campus->id !== null ? $campus : null;
    }

    public function update(UpdateFinanceSettingsRequest $request): RedirectResponse
    {
        $settings = FinanceSetting::current();
        $settings->update($request->validated());

        Inertia::flash('success', 'Finance settings saved.');

        return back();
    }
}
