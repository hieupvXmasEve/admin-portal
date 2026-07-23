<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\CurriculumModule;
use App\Models\CurriculumVersion;
use App\Modules\Academic\Catalog\Actions\AttachCurriculumModulesAction;
use App\Modules\Academic\Catalog\Actions\DetachCurriculumModulesAction;
use App\Modules\Academic\Catalog\Actions\UpdateCurriculumModuleAction;
use App\Modules\Academic\Catalog\Http\Requests\AttachCurriculumModulesRequest;
use App\Modules\Academic\Catalog\Http\Requests\DetachCurriculumModulesRequest;
use App\Modules\Academic\Catalog\Http\Requests\UpdateCurriculumModuleRequest;
use Illuminate\Http\RedirectResponse;

class CurriculumModuleController extends Controller
{
    public function attach(
        AttachCurriculumModulesRequest $request,
        CurriculumVersion $curriculumVersion,
        AttachCurriculumModulesAction $attach,
    ): RedirectResponse {
        $attach->handle($curriculumVersion, $request->validated('modules'));

        return back()->with('success', 'Modules attached to curriculum successfully');
    }

    public function detach(
        DetachCurriculumModulesRequest $request,
        CurriculumVersion $curriculumVersion,
        DetachCurriculumModulesAction $detach,
    ): RedirectResponse {
        $detach->handle($curriculumVersion, $request->validated('module_ids'));

        return back()->with('success', 'Modules detached from curriculum successfully');
    }

    public function update(
        UpdateCurriculumModuleRequest $request,
        CurriculumModule $curriculumModule,
        UpdateCurriculumModuleAction $update,
    ): RedirectResponse {
        $update->handle($curriculumModule, $request->validated());

        return back()->with('success', 'Module updated successfully');
    }
}
