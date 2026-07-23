<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CurriculumModule;
use App\Models\CurriculumVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** @deprecated Curriculum module grouping is owned by Academic Catalog. */
class CurriculumModuleController extends Controller
{
    public function attach(Request $request, CurriculumVersion $curriculumVersion): RedirectResponse
    {
        $validated = $request->validate([
            'modules' => ['required', 'array'],
            'modules.*.module_id' => ['required', 'exists:modules,id'],
            'modules.*.year_level' => ['nullable', 'integer', 'min:1', 'max:9'],
            'modules.*.semester_number' => ['nullable', 'integer', 'min:1', 'max:9'],
            'modules.*.is_required' => ['boolean'],
            'modules.*.group_name' => ['nullable', 'string', 'max:255'],
            'modules.*.order' => ['required', 'integer', 'min:0'],
            'modules.*.note' => ['nullable', 'string'],
        ]);

        foreach ($validated['modules'] as $moduleData) {
            CurriculumModule::updateOrCreate(
                [
                    'curriculum_version_id' => $curriculumVersion->id,
                    'module_id' => $moduleData['module_id'],
                ],
                [
                    'year_level' => $moduleData['year_level'] ?? null,
                    'semester_number' => $moduleData['semester_number'] ?? null,
                    'is_required' => $moduleData['is_required'] ?? true,
                    'group_name' => $moduleData['group_name'] ?? null,
                    'order' => $moduleData['order'],
                    'note' => $moduleData['note'] ?? null,
                ]
            );
        }

        return redirect()
            ->back()
            ->with('success', 'Modules attached to curriculum successfully');
    }

    public function detach(Request $request, CurriculumVersion $curriculumVersion): RedirectResponse
    {
        $validated = $request->validate([
            'module_ids' => ['required', 'array'],
            'module_ids.*' => ['required', 'exists:modules,id'],
        ]);

        CurriculumModule::where('curriculum_version_id', $curriculumVersion->id)
            ->whereIn('module_id', $validated['module_ids'])
            ->delete();

        return redirect()
            ->back()
            ->with('success', 'Modules detached from curriculum successfully');
    }

    public function update(Request $request, CurriculumModule $curriculumModule): RedirectResponse
    {
        $validated = $request->validate([
            'year_level' => ['nullable', 'integer', 'min:1', 'max:9'],
            'semester_number' => ['nullable', 'integer', 'min:1', 'max:9'],
            'is_required' => ['boolean'],
            'group_name' => ['nullable', 'string', 'max:255'],
            'order' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        $curriculumModule->update($validated);

        return redirect()
            ->back()
            ->with('success', 'Module updated successfully');
    }
}
