<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Modules\Finance\Actions\UpdateDngCampusMappingAction;
use App\Modules\Finance\Http\Requests\Dng\UpdateDngCampusMappingRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class DngCampusMappingController extends Controller
{
    public function update(
        UpdateDngCampusMappingRequest $request,
    ): RedirectResponse {
        $mapping = UpdateDngCampusMappingAction::run([
            'campus_id' => (int) $this->currentCampus()->id,
            'provider_code' => $request->validated('provider_code'),
            'actor_user_id' => $request->user()?->id,
        ]);

        Inertia::flash('success', "DNG campus code [{$mapping->provider_code}] saved for the selected campus.");

        return back();
    }

    private function currentCampus(): Campus
    {
        $campus = app()->bound('campus') ? app('campus') : null;

        abort_unless($campus instanceof Campus && $campus->id !== null, 409, 'Select a campus before managing its DNG mapping.');

        return $campus;
    }
}
