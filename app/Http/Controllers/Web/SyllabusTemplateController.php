<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyllabusTemplate\StoreSyllabusTemplateRequest;
use App\Http\Requests\SyllabusTemplate\UpdateSyllabusTemplateRequest;
use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SyllabusTemplateController extends Controller
{
    // Inertia pages
    public function pageIndex(Request $request): Response
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(5, min(100, $perPage));

        $query = SyllabusTemplate::query()->with(['unit']);

        // Filters: is_active (bool), unit_id (int), search (string)
        $search = (string) $request->query('search', '');
        $unitId = $request->query('unit_id');
        $isActive = $request->query('is_active'); // can be '1' | '0' | null

        if ($unitId !== null && $unitId !== '') {
            $query->where('unit_id', (int) $unitId);
        }

        if ($isActive !== null && $isActive !== '') {
            // '0' casts to false, '1' to true
            $query->where('is_active', (bool) $isActive);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('version', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('unit', function ($uq) use ($search) {
                        $uq->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        $items = $query
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $units = Unit::query()->orderBy('code')->get(['id', 'code', 'name']);

        return Inertia::render('syllabus/TemplatesIndex', [
            'items' => $items,
            'filters' => [
                'search' => $search,
                'unit_id' => $unitId !== null && $unitId !== '' ? (int) $unitId : null,
                'is_active' => $isActive !== null && $isActive !== '' ? (string) $isActive : null,
                'per_page' => $perPage,
            ],
            'units' => $units,
        ]);
    }

    public function pageCreate(): Response
    {
        $units = Unit::query()->orderBy('code')->get(['id', 'code', 'name']);
        return Inertia::render('syllabus/TemplatesCreate', [
            'assessmentTypes' => AssessmentComponent::TYPES,
            'units' => $units,
        ]);
    }

    public function pageEdit(SyllabusTemplate $syllabusTemplate): Response
    {
        $units = Unit::query()->orderBy('code')->get(['id', 'code', 'name']);
        return Inertia::render('syllabus/TemplatesEdit', [
            'unit' => $syllabusTemplate->unit,
            'syllabusTemplate' => $syllabusTemplate->load(['unit', 'applicableProgram', 'applicableCampus', 'creator', 'sourceTemplate', 'assessmentComponents.details']),
            'assessmentTypes' => AssessmentComponent::TYPES,
            'units' => $units,
        ]);
    }

    public function pageShow(SyllabusTemplate $syllabusTemplate): Response
    {
        return Inertia::render('syllabus/TemplatesShow', [
            'unit' => $syllabusTemplate->unit,
            'template' => $syllabusTemplate->load(['unit', 'applicableProgram', 'applicableCampus', 'creator', 'sourceTemplate', 'assessmentComponents.details']),
        ]);
    }

    // JSON endpoints (reused under web routes)
    public function index(Unit $unit): JsonResponse
    {
        $templates = SyllabusTemplate::query()
            ->where('unit_id', $unit->getKey())
            ->with(['unit', 'applicableProgram', 'applicableCampus', 'creator', 'sourceTemplate'])
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function store(StoreSyllabusTemplateRequest $request, ?Unit $unit = null): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        // Support both routes: with /units/{unit} and direct POST body unit_id
        $unitId = $unit?->getKey() ?? ($data['unit_id'] ?? $request->integer('unit_id'));
        if (empty($unitId)) {
            return back()->withErrors(['unit_id' => 'Unit is required'])->withInput();
        }

        $data['unit_id'] = (int) $unitId;
        $data['created_by'] = auth()->id();

        $template = null;
        DB::transaction(function () use (&$template, $data, $request) {
            $template = SyllabusTemplate::create($data);

            // Create assessment components if provided
            $components = $data['assessment_components'] ?? $request->input('assessment_components', []);
            if (is_array($components) && ! empty($components)) {
                foreach ($components as $idx => $comp) {
                    if (! is_array($comp)) {
                        continue;
                    }

                    $component = new AssessmentComponent([
                        'name' => $comp['name'] ?? null,
                        'weight' => $comp['weight'] ?? 0,
                        'type' => $comp['type'] ?? 'other',
                        'sort_order' => $idx,
                    ]);
                    $component->syllabus_template_id = $template->getKey();
                    $component->save();

                    $details = $comp['details'] ?? [];
                    if (is_array($details) && ! empty($details)) {
                        foreach ($details as $d) {
                            if (! is_array($d)) {
                                continue;
                            }
                            AssessmentComponentDetail::create([
                                'assessment_component_id' => $component->getKey(),
                                'name' => $d['name'] ?? '',
                                'weight' => $d['weight'] ?? null,
                                'max_points' => 100.00
                            ]);
                        }
                    } else {
                        // Create default detail if no details provided
                        AssessmentComponentDetail::create([
                            'assessment_component_id' => $component->getKey(),
                            'name' => $component->name,
                            'weight' => 100.00,
                            'max_points' => 100.00
                        ]);
                    }
                }
            }
        });

        if (! empty($data['is_default'])) {
            $this->setDefaultForUnit($template);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $template->fresh()], 201);
        }

        return redirect()->route('syllabus_templates.index')->with('success', 'Template created');
    }

    public function show(Request $request, SyllabusTemplate $syllabusTemplate): JsonResponse|Response
    {
        $syllabusTemplate->load(['unit', 'applicableProgram', 'applicableCampus', 'creator', 'sourceTemplate', 'assessmentComponents.details']);

        if ($request->wantsJson()) {
            return response()->json(['data' => $syllabusTemplate]);
        }

        return Inertia::render('syllabus/TemplatesShow', [
            'unit' => $syllabusTemplate->unit,
            'template' => $syllabusTemplate,
        ]);
    }

    public function update(UpdateSyllabusTemplateRequest $request, SyllabusTemplate $syllabusTemplate): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use (&$syllabusTemplate, $data, $request) {
            // Update template basic fields
            $syllabusTemplate->fill($data);
            $syllabusTemplate->save();

            // Optional: sync assessment components if provided
            if ($request->has('assessment_components') && is_array($request->input('assessment_components'))) {
                $incomingComponents = $request->input('assessment_components', []);

                // Map existing components by id
                $existingComponents = \App\Models\AssessmentComponent::where('syllabus_template_id', $syllabusTemplate->getKey())
                    ->get()
                    ->keyBy('id');

                $keepComponentIds = [];

                foreach ($incomingComponents as $idx => $compData) {
                    if (!is_array($compData)) continue;

                    $componentId = $compData['id'] ?? null;
                    $component = null;

                    if ($componentId && isset($existingComponents[$componentId])) {
                        $component = $existingComponents[$componentId];
                        $component->fill([
                            'name' => $compData['name'] ?? $component->name,
                            'weight' => $compData['weight'] ?? $component->weight,
                            'type' => $compData['type'] ?? $component->type,
                            'sort_order' => $idx,
                        ]);
                        $component->save();
                    } else {
                        $component = new \App\Models\AssessmentComponent([
                            'name' => $compData['name'] ?? null,
                            'weight' => $compData['weight'] ?? 0,
                            'type' => $compData['type'] ?? 'other',
                            'sort_order' => $idx,
                        ]);
                        $component->syllabus_template_id = $syllabusTemplate->getKey();
                        $component->save();
                    }

                    $keepComponentIds[] = $component->getKey();

                    // Sync details for this component
                    $incomingDetails = $compData['details'] ?? [];
                    $existingDetails = \App\Models\AssessmentComponentDetail::where('assessment_component_id', $component->getKey())
                        ->get()
                        ->keyBy('id');
                    $keepDetailIds = [];

                    if (is_array($incomingDetails) && !empty($incomingDetails)) {
                        foreach ($incomingDetails as $d) {
                            if (!is_array($d)) continue;
                            $detailId = $d['id'] ?? null;
                            if ($detailId && isset($existingDetails[$detailId])) {
                                $detail = $existingDetails[$detailId];
                                $detail->fill([
                                    'name' => $d['name'] ?? $detail->name,
                                    'weight' => $d['weight'] ?? $detail->weight,
                                ]);
                                $detail->save();
                            } else {
                                $detail = new \App\Models\AssessmentComponentDetail([
                                    'assessment_component_id' => $component->getKey(),
                                    'name' => $d['name'] ?? '',
                                    'weight' => $d['weight'] ?? null,
                                ]);
                                $detail->save();
                            }
                            $keepDetailIds[] = $detail->getKey();
                        }
                    } else {
                        // If this is a new component (no componentId) and no details provided,
                        // create a default detail
                        if (!$componentId) {
                            $detail = new \App\Models\AssessmentComponentDetail([
                                'assessment_component_id' => $component->getKey(),
                                'name' => $component->name,
                                'weight' => 100.00,
                                'max_points' => 100.00
                            ]);
                            $detail->save();
                            $keepDetailIds[] = $detail->getKey();
                        }
                    }

                    // Delete details not present
                    if (!empty($keepDetailIds)) {
                        \App\Models\AssessmentComponentDetail::where('assessment_component_id', $component->getKey())
                            ->whereNotIn('id', $keepDetailIds)
                            ->delete();
                    } else {
                        // If no details in payload, remove all existing details and create default
                        \App\Models\AssessmentComponentDetail::where('assessment_component_id', $component->getKey())->delete();

                        // Create default detail
                        \App\Models\AssessmentComponentDetail::create([
                            'assessment_component_id' => $component->getKey(),
                            'name' => $component->name,
                            'weight' => 100.00,
                            'max_points' => 100.00
                        ]);
                    }
                }

                // Delete components not present
                if (!empty($keepComponentIds)) {
                    $toDelete = \App\Models\AssessmentComponent::where('syllabus_template_id', $syllabusTemplate->getKey())
                        ->whereNotIn('id', $keepComponentIds)
                        ->get();
                    foreach ($toDelete as $delComp) {
                        \App\Models\AssessmentComponentDetail::where('assessment_component_id', $delComp->getKey())->delete();
                        $delComp->delete();
                    }
                } else {
                    // No components in payload => remove all
                    $all = \App\Models\AssessmentComponent::where('syllabus_template_id', $syllabusTemplate->getKey())->get();
                    foreach ($all as $delComp) {
                        \App\Models\AssessmentComponentDetail::where('assessment_component_id', $delComp->getKey())->delete();
                        $delComp->delete();
                    }
                }
            }
        });

        if (array_key_exists('is_default', $data) && $syllabusTemplate->is_default) {
            $this->setDefaultForUnit($syllabusTemplate);
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $syllabusTemplate->fresh()]);
        }

        return redirect()->route('syllabus_templates.edit', $syllabusTemplate)
            ->with('success', 'Template updated');
    }

    public function destroy(Request $request, SyllabusTemplate $syllabusTemplate): JsonResponse|RedirectResponse
    {
        $unit = $syllabusTemplate->unit;
        $syllabusTemplate->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Deleted']);
        }

        return redirect()->route('syllabus_templates.index')->with('success', 'Template deleted');
    }

    public function toggleActive(Request $request, SyllabusTemplate $syllabusTemplate): JsonResponse|RedirectResponse
    {
        $syllabusTemplate->is_active = ! $syllabusTemplate->is_active;
        $syllabusTemplate->save();

        if ($request->wantsJson()) {
            return response()->json(['data' => $syllabusTemplate]);
        }
        return back()->with('success', 'Status updated');
    }

    public function setDefault(Request $request, SyllabusTemplate $syllabusTemplate): JsonResponse|RedirectResponse
    {
        $this->setDefaultForUnit($syllabusTemplate);

        if ($request->wantsJson()) {
            return response()->json(['data' => $syllabusTemplate->fresh()]);
        }
        return back()->with('success', 'Default set');
    }

    public function clone(Request $request, SyllabusTemplate $syllabusTemplate): JsonResponse|RedirectResponse
    {
        $cloned = $syllabusTemplate->replicate([
            'is_default',
            'is_active',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
        $cloned->is_default = false;
        $cloned->is_active = true;
        $cloned->created_by = auth()->id();
        $cloned->source_template_id = $syllabusTemplate->getKey();
        $cloned->version = $this->nextVersion($syllabusTemplate->version);
        $cloned->save();

        if ($request->wantsJson()) {
            return response()->json(['data' => $cloned], 201);
        }
        return back()->with('success', 'Template cloned');
    }

    private function setDefaultForUnit(SyllabusTemplate $template): void
    {
        DB::transaction(function () use ($template) {
            SyllabusTemplate::where('unit_id', $template->unit_id)
                ->where('id', '!=', $template->getKey())
                ->update(['is_default' => false]);

            $template->is_default = true;
            $template->save();
        });
    }

    private function nextVersion(?string $current): string
    {
        if (empty($current)) {
            return '1.0';
        }

        $parts = array_map('intval', explode('.', $current));
        if (empty($parts)) {
            return $current; // fallback
        }
        $parts[count($parts) - 1] = ($parts[count($parts) - 1] ?? 0) + 1;
        return implode('.', $parts);
    }
}
