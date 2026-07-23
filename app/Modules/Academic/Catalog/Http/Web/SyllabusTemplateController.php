<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

use App\Http\Requests\SyllabusTemplate\StoreSyllabusTemplateRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Unit;
use App\Modules\Academic\Catalog\Actions\CreateSyllabusTemplateAction;
use App\Modules\Academic\Catalog\Http\Requests\ListSyllabusTemplatesRequest;
use App\Modules\Academic\Catalog\Queries\ListSyllabusTemplatesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalog owns the list and creation flow; inherited endpoints remain during
 * the staged migration of update, clone, and grading-scheme operations.
 */
class SyllabusTemplateController extends \App\Http\Controllers\Web\SyllabusTemplateController
{
    public function pageIndex(ListSyllabusTemplatesRequest $request, ListSyllabusTemplatesQuery $list): Response
    {
        $filters = $request->validated();

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
        CreateSyllabusTemplateAction $create,
    ): JsonResponse|RedirectResponse {
        $template = $create->handle([
            ...$request->validated(),
            'created_by' => auth()->id(),
        ]);

        if ($request->wantsJson()) {
            return ApiResponse::success($template, status: 201);
        }

        return redirect()->route('syllabus_templates.index')->with('success', 'Template created');
    }
}
