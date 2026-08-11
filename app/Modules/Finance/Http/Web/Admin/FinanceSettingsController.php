<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Http\Requests\Settings\UpdateFinanceSettingsRequest;
use App\Modules\Finance\Models\FinanceSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceSettingsController extends Controller
{
    public function show(Request $request): Response
    {
        $settings = FinanceSetting::current();

        return Inertia::render('Finance/Settings/Show', [
            'settings' => [
                'credit_offset_enabled' => $settings->credit_offset_enabled,
                'credit_offset_min_balance' => (float) $settings->credit_offset_min_balance,
                'updated_at' => $settings->updated_at?->toIso8601String(),
            ],
            'can_manage' => $request->user()?->can('manage_finance_settings') ?? false,
        ]);
    }

    public function update(UpdateFinanceSettingsRequest $request): RedirectResponse
    {
        $settings = FinanceSetting::current();
        $settings->update($request->validated());

        Inertia::flash('success', 'Finance settings saved.');

        return back();
    }
}
