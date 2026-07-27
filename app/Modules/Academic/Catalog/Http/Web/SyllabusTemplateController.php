<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyllabusTemplate\PreviewGradingSchemeRequest;
use App\Http\Requests\SyllabusTemplate\StoreSyllabusTemplateRequest;
use App\Http\Requests\SyllabusTemplate\UpdateSyllabusTemplateRequest;
use App\Http\Responses\ApiResponse;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Modules\Academic\Catalog\Actions\CreateSyllabusTemplateAction;
use App\Modules\Academic\Catalog\Actions\ManageSyllabusTemplateAction;
use App\Modules\Academic\Catalog\Actions\PreviewSyllabusGradingSchemeAction as CatalogPreviewSyllabusGradingSchemeAction;
use App\Modules\Academic\Catalog\Actions\UpdateSyllabusTemplateAction;
use App\Modules\Academic\Catalog\Http\Requests\ListSyllabusTemplatesRequest;
use App\Modules\Academic\Catalog\Queries\ListSyllabusTemplatesQuery;
use App\Shared\Contracts\Academic\AssessmentDefinitionWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalog owns Syllabus Template staff and API workflows.
 */
class SyllabusTemplateController extends Controller
{
    public function __construct(private readonly AssessmentDefinitionWriter $assessmentDefinitions) {}

    public function index(Unit $unit): JsonResponse
    {
        $templates = SyllabusTemplate::query()
            ->where('unit_id', $unit->getKey())
            ->with(['unit', 'applicableProgram', 'applicableCampus', 'creator', 'sourceTemplate'])
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success($templates);
    }

    public function pageIndex(ListSyllabusTemplatesRequest $request): Response
    {
        $filters = $request->validated();

        return Inertia::render('Syllabus/TemplatesIndex', [
            'items' => app(ListSyllabusTemplatesQuery::class)->handle($filters),
            'filters' => (object) [
                'search' => $filters['search'] ?? '',
                'unit_id' => isset($filters['unit_id']) ? (string) $filters['unit_id'] : '',
                'is_active' => $filters['is_active'] ?? '',
                'sort' => $filters['sort'] ?? null,
                'direction' => $filters['direction'] ?? null,
                'per_page' => $filters['per_page'] ?? 10,
            ],
            'units' => Unit::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function pageCreate(): Response
    {
        return Inertia::render('Syllabus/TemplatesCreate', [
            'assessmentTypes' => $this->assessmentDefinitions->types(),
            'units' => Unit::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function pageEdit(SyllabusTemplate $syllabusTemplate): Response|RedirectResponse
    {
        if ($syllabusTemplate->isLockedForEditing()) {
            Inertia::flash(
                'error',
                'This syllabus template cannot be edited because it is assigned to a course offering with completed class sessions.',
            );

            return redirect()->route('syllabus_templates.show', $syllabusTemplate);
        }

        return Inertia::render('Syllabus/TemplatesEdit', [
            'unit' => $syllabusTemplate->unit,
            'syllabusTemplate' => $this->loadTemplate($syllabusTemplate),
            'assessmentTypes' => $this->assessmentDefinitions->types(),
            'units' => Unit::query()->orderBy('code')->get(['id', 'code', 'name']),
            'can_edit' => true,
        ]);
    }

    public function pageShow(SyllabusTemplate $syllabusTemplate): Response
    {
        $template = $this->loadTemplate($syllabusTemplate);

        return Inertia::render('Syllabus/TemplatesShow', [
            'unit' => $template->unit,
            'template' => $template,
            'can_edit' => ! $template->isLockedForEditing(),
        ]);
    }

    public function store(StoreSyllabusTemplateRequest $request): JsonResponse|RedirectResponse
    {
        $template = app(CreateSyllabusTemplateAction::class)->handle([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        if ($request->wantsJson()) {
            return ApiResponse::success($template, status: 201);
        }

        Inertia::flash('success', 'Template created');

        return redirect()->route('syllabus_templates.index');
    }

    public function show(Request $request, SyllabusTemplate $syllabusTemplate): JsonResponse|Response
    {
        $template = $this->loadTemplate($syllabusTemplate);

        if ($request->wantsJson()) {
            return ApiResponse::success($template);
        }

        return Inertia::render('Syllabus/TemplatesShow', [
            'unit' => $template->unit,
            'template' => $template,
            'can_edit' => ! $template->isLockedForEditing(),
        ]);
    }

    public function update(UpdateSyllabusTemplateRequest $request, SyllabusTemplate $syllabusTemplate): JsonResponse|RedirectResponse
    {
        $template = app(UpdateSyllabusTemplateAction::class)->handle($syllabusTemplate, $request->validated());

        if ($request->wantsJson()) {
            return ApiResponse::success($template);
        }

        Inertia::flash('success', 'Template updated');

        return redirect()->route('syllabus_templates.edit', $syllabusTemplate);
    }

    public function destroy(
        Request $request,
        SyllabusTemplate $syllabusTemplate,
    ): JsonResponse|RedirectResponse {
        app(ManageSyllabusTemplateAction::class)->delete($syllabusTemplate);

        if ($request->wantsJson()) {
            return ApiResponse::success(message: 'Deleted');
        }

        Inertia::flash('success', 'Template deleted');

        return redirect()->route('syllabus_templates.index');
    }

    public function toggleActive(
        Request $request,
        SyllabusTemplate $syllabusTemplate,
    ): JsonResponse|RedirectResponse {
        $template = app(ManageSyllabusTemplateAction::class)->toggleActive($syllabusTemplate);

        if ($request->wantsJson()) {
            return ApiResponse::success($template);
        }

        Inertia::flash('success', 'Status updated');

        return back();
    }

    public function setDefault(
        Request $request,
        SyllabusTemplate $syllabusTemplate,
    ): JsonResponse|RedirectResponse {
        $template = app(ManageSyllabusTemplateAction::class)->setDefault($syllabusTemplate);

        if ($request->wantsJson()) {
            return ApiResponse::success($template);
        }

        Inertia::flash('success', 'Default set');

        return back();
    }

    public function clone(
        Request $request,
        SyllabusTemplate $syllabusTemplate,
    ): JsonResponse|RedirectResponse {
        $template = app(ManageSyllabusTemplateAction::class)->clone($syllabusTemplate, auth()->id());

        if ($request->wantsJson()) {
            return ApiResponse::success($template, status: 201);
        }

        Inertia::flash('success', 'Template cloned');

        return back();
    }

    public function gradingSchemeOptions(): JsonResponse
    {
        return ApiResponse::success([
            'engines' => [
                ['value' => 'default', 'label' => 'Default weighted percentage'],
                ['value' => 'metropolia_v1', 'label' => 'Metropolia v1'],
                ['value' => 'metropolia_v2', 'label' => 'Metropolia v2 (formula)'],
            ],
        ]);
    }

    public function previewGradingScheme(PreviewGradingSchemeRequest $request): JsonResponse
    {
        $result = app(CatalogPreviewSyllabusGradingSchemeAction::class)->handle(
            $request->validated('grading_scheme'),
            $request->validated('component_scores') ?? [],
        );

        if (($result['valid'] ?? false) === false) {
            $errors = array_map(
                static fn (string $detail): array => ['code' => 'INVALID_SCHEME', 'detail' => $detail],
                $result['errors'] ?? [],
            );

            return ApiResponse::error('Invalid grading scheme.', $errors, 422);
        }

        return ApiResponse::success($result);
    }

    public function previewExistingGradingScheme(PreviewGradingSchemeRequest $request, SyllabusTemplate $syllabusTemplate): JsonResponse
    {
        return $this->previewGradingScheme($request);
    }

    private function loadTemplate(SyllabusTemplate $syllabusTemplate): SyllabusTemplate
    {
        return $syllabusTemplate->load([
            'unit',
            'applicableProgram',
            'applicableCampus',
            'creator',
            'sourceTemplate',
            'assessmentComponents.details',
        ]);
    }
}
