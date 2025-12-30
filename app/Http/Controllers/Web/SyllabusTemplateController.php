<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\SyllabusTemplate\CreateSyllabusTemplateAction;
use App\Actions\SyllabusTemplate\GetSyllabusTemplateListAction;
use App\Actions\SyllabusTemplate\UpdateSyllabusTemplateAction;
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
    public function pageIndex(Request $request, GetSyllabusTemplateListAction $action): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'unit_id' => 'nullable|string',
            'is_active' => 'nullable|string|in:1,0,all',
            'sort' => 'nullable|string|in:title,version,is_active,created_at',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $items = $action->execute($validated);

        $units = Unit::query()->orderBy('code')->get(['id', 'code', 'name']);

        return Inertia::render('syllabus/TemplatesIndex', [
            'items' => $items,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'unit_id' => $validated['unit_id'] ?? 'all',
                'is_active' => $validated['is_active'] ?? 'all',
                'sort' => $validated['sort'] ?? null,
                'direction' => $validated['direction'] ?? null,
                'per_page' => $validated['per_page'] ?? 10,
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

    public function store(StoreSyllabusTemplateRequest $request, CreateSyllabusTemplateAction $action): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();

        $template = $action->execute($data);

        if ($request->wantsJson()) {
            return response()->json(['data' => $template], 201);
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

    public function update(UpdateSyllabusTemplateRequest $request, SyllabusTemplate $syllabusTemplate, UpdateSyllabusTemplateAction $action): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        $template = $action->execute($syllabusTemplate, $data);

        if ($request->wantsJson()) {
            return response()->json(['data' => $template]);
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
