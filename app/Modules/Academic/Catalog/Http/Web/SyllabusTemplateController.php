<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

use App\Actions\SyllabusTemplate\CreateSyllabusTemplateAction;
use App\Actions\SyllabusTemplate\GetSyllabusTemplateListAction;
use App\Actions\SyllabusTemplate\UpdateSyllabusTemplateAction;
use App\Http\Requests\SyllabusTemplate\StoreSyllabusTemplateRequest;
use App\Http\Requests\SyllabusTemplate\UpdateSyllabusTemplateRequest;
use App\Http\Responses\ApiResponse;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Modules\Academic\Catalog\Actions\ManageSyllabusTemplateAction;
use App\Modules\Academic\Catalog\Http\Requests\ListSyllabusTemplatesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalog owns the list and creation flow; inherited endpoints remain during
 * the staged migration of update, clone, and grading-scheme operations.
 */
class SyllabusTemplateController extends \App\Http\Controllers\Web\SyllabusTemplateController
{
    public function pageIndex(Request $request, GetSyllabusTemplateListAction $legacyList): Response
    {
        $filters = $request->validate((new ListSyllabusTemplatesRequest)->rules());

        return Inertia::render('syllabus/TemplatesIndex', [
            'items' => $list->handle($filters),
            'filters' => [
                'search' => $filters['search'] ?? null,
                'unit_id' => $filters['unit_id'] ?? 'all',
                'is_active' => $filters['is_active'] ?? 'all',
                'sort' => $filters['sort'] ?? null,
                'direction' => $filters['direction'] ?? null,
                'per_page' => $filters['per_page'] ?? 10,
            ],
            'units' => Unit::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(
        StoreSyllabusTemplateRequest $request,
        CreateSyllabusTemplateAction $legacyCreate,
    ): JsonResponse|RedirectResponse {
        $template = app(\App\Modules\Academic\Catalog\Actions\CreateSyllabusTemplateAction::class)->handle([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        if ($request->wantsJson()) {
            return ApiResponse::success($template, status: 201);
        }

        Inertia::flash('success', 'Template created');

        return redirect()->route('syllabus_templates.index');
    }

    public function update(
        UpdateSyllabusTemplateRequest $request,
        SyllabusTemplate $syllabusTemplate,
        UpdateSyllabusTemplateAction $legacyUpdate,
    ): JsonResponse|RedirectResponse {
        $template = app(\App\Modules\Academic\Catalog\Actions\UpdateSyllabusTemplateAction::class)->handle($syllabusTemplate, $request->validated());

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
}
