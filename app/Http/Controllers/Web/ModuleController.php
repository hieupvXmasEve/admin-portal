<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Module\StoreModuleRequest;
use App\Http\Requests\Module\UpdateModuleRequest;
use App\Models\Campus;
use App\Models\Module;
use App\Models\Unit;
use App\Services\ModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function __construct(
        private ModuleService $moduleService
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'campus_id' => 'nullable|integer|exists:campuses,id',
            'sort' => 'nullable|string|in:code,name,total_credits,grading_type,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $modules = Module::query()
            ->with(['campus', 'prerequisiteModule'])
            ->withCount('units')
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($validated['campus_id'] ?? null, function ($query, $campusId) {
                $query->where('campus_id', $campusId);
            })
            ->when($validated['sort'] ?? null, function ($query, $sort) use ($validated) {
                $direction = $validated['direction'] ?? 'asc';
                $query->orderBy($sort, $direction);
            }, function ($query) {
                $query->orderBy('code', 'asc');
            })
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return Inertia::render('Admin/Modules/Index', [
            'modules' => $modules,
            'campuses' => Campus::select('id', 'name', 'code')->orderBy('name')->get(),
            'filters' => [
                'search' => $validated['search'] ?? '',
                'campus_id' => $validated['campus_id'] ?? '',
                'sort' => $validated['sort'] ?? '',
                'direction' => $validated['direction'] ?? 'asc',
                'per_page' => $validated['per_page'] ?? 15,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Modules/Create', [
            'campuses' => Campus::select('id', 'name', 'code')->orderBy('name')->get(),
            'modules' => Module::select('id', 'code', 'name')->orderBy('code')->get(), // For prerequisite
            'units' => Unit::select('id', 'code', 'name', 'credit_points')
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(StoreModuleRequest $request): RedirectResponse
    {
        $module = $this->moduleService->create($request->validated());

        return redirect()
            ->route('admin.modules.show', $module->id)
            ->with('success', 'Module created successfully');
    }

    public function show(Module $module): Response
    {
        $module->load([
            'campus',
            'units' => function ($query) {
                $query->orderBy('module_units.order');
            },
            'prerequisiteModule',
            'dependentModules',
            'curriculumModules.curriculumVersion.program',
            'curriculumModules.curriculumVersion.specialization',
        ]);

        return Inertia::render('Admin/Modules/Show', [
            'module' => $module,
            'availableUnits' => Unit::select('id', 'code', 'name', 'credit_points')
                ->where('unit_type', '!=', 'egc') // cannot get unit type is 'egc'
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function edit(Module $module): Response
    {
        $module->load(['units', 'campus']);

        return Inertia::render('Admin/Modules/Edit', [
            'module' => $module,
            'campuses' => Campus::select('id', 'name', 'code')->orderBy('name')->get(),
            'modules' => Module::select('id', 'code', 'name')
                ->where('id', '!=', $module->id) // Exclude self from prerequisite
                ->orderBy('code')
                ->get(),
            'units' => Unit::select('id', 'code', 'name', 'credit_points')
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function update(UpdateModuleRequest $request, Module $module): RedirectResponse
    {
        $module = $this->moduleService->update($module, $request->validated());

        return redirect()
            ->route('admin.modules.show', $module->id)
            ->with('success', 'Module updated successfully');
    }

    public function destroy(Module $module): RedirectResponse
    {
        if ($module->curriculumModules()->exists()) {
            return redirect()
                ->back()
                ->with('error', 'Cannot delete module that is assigned to curriculum versions');
        }

        $this->moduleService->delete($module);

        return redirect()
            ->route('admin.modules.index')
            ->with('success', 'Module deleted successfully');
    }

    public function syncUnits(Request $request, Module $module): RedirectResponse
    {
        $validated = $request->validate([
            'units' => ['required', 'array'],
            'units.*.unit_id' => ['required', 'exists:units,id'],
            'units.*.grading_type' => ['required', 'in:grade,pass_fail'],
            'units.*.weight' => ['nullable', 'numeric', 'min:0'],
            'units.*.order' => ['required', 'integer', 'min:0'],
        ]);

        $this->moduleService->syncUnits($module, $validated['units']);

        return redirect()
            ->back()
            ->with('success', 'Module units updated successfully');
    }
}
