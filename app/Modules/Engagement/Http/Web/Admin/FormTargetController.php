<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseOffering;
use App\Models\Form;
use App\Models\FormTarget;
use App\Modules\Engagement\Actions\Forms\ActivateFormTargetAction;
use App\Modules\Engagement\Actions\Forms\CreateFormTargetAction;
use App\Modules\Engagement\Http\Requests\Forms\ListFormTargetsRequest;
use App\Modules\Engagement\Http\Requests\Forms\StoreFormTargetRequest;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Institution\DepartmentReferenceReader;
use Inertia\Inertia;

class FormTargetController extends Controller
{
    public function __construct(
        private readonly DepartmentReferenceReader $departments,
        private readonly AcademicPeriodReader $academicPeriods,
    ) {}

    public function index(ListFormTargetsRequest $request)
    {
        $this->authorize('view_form');

        $validated = $request->validated();

        $query = FormTarget::query()
            ->forCurrentCampus()
            ->with([
                'form:id,code,title,type,status',
                'formVersion',
                'campus',
                'semester',
                'scope' => function ($morphTo) {
                    $morphTo->morphWith([
                        CourseOffering::class => ['unit'],
                    ]);
                },
            ])
            ->latest('created_at');

        if (! empty($validated['scope_type']) && $validated['scope_type'] !== 'all') {
            $query->where('scope_type', $validated['scope_type']);
        }

        if (! empty($validated['status']) && $validated['status'] !== 'all') {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['created_from'])) {
            $query->where('created_at', '>=', $validated['created_from']);
        }

        if (! empty($validated['created_to'])) {
            $query->where('created_at', '<=', $validated['created_to']);
        }

        $runs = $query->paginate($validated['per_page'] ?? 15)->withQueryString();

        return Inertia::render('Forms/Runs/Index', [
            'runs' => $runs,
            'filters' => [
                'scope_type' => $validated['scope_type'] ?? 'all',
                'status' => $validated['status'] ?? 'all',
                'created_from' => $validated['created_from'] ?? null,
                'created_to' => $validated['created_to'] ?? null,
                'per_page' => $validated['per_page'] ?? 15,
            ],
        ]);
    }

    public function create()
    {
        $this->authorize('edit_form');

        return Inertia::render('Forms/Runs/Create', [
            'forms' => Form::query()
                ->where('status', 'active')
                ->whereHas('latestPublishedVersion')
                ->orderBy('title')
                ->get(['id', 'title', 'type']),
            'semesters' => collect($this->academicPeriods->selectable())
                ->sortBy('start_date')
                ->map(fn ($period): array => [
                    'id' => $period->id,
                    'name' => $period->name,
                    'code' => $period->code,
                    'start_date' => $period->start_date?->toDateString(),
                    'end_date' => $period->end_date?->toDateString(),
                ])
                ->values(),
            'departments' => collect($this->departments->allActive())->map->toArray(),
        ]);
    }

    public function store(StoreFormTargetRequest $request, CreateFormTargetAction $action)
    {
        $this->authorize('edit_form');
        $validated = $request->validated();

        if ($campusId = session('current_campus_id')) {
            $validated['campus_id'] = $campusId;
        }

        $action->execute($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Form Run created successfully.',
            ]);
        }

        return redirect()->route('forms.admin.runs.index')->with('success', 'Form Run created successfully.');
    }

    public function activate(FormTarget $target, ActivateFormTargetAction $action)
    {
        $this->authorize('edit_form');
        $this->assertTargetCampus($target);

        $action->execute($target);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Run activated.',
            ]);
        }

        return back()->with('success', 'Run activated.');
    }

    public function close(FormTarget $target)
    {
        $this->authorize('edit_form');
        $this->assertTargetCampus($target);
        $target->update(['status' => 'closed']);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Run closed.',
            ]);
        }

        return back()->with('success', 'Run closed.');
    }

    private function assertTargetCampus(FormTarget $target): void
    {
        $currentCampusId = session('current_campus_id');

        if ($currentCampusId !== null && $target->campus_id !== null) {
            abort_unless((int) $target->campus_id === (int) $currentCampusId, 404);
        }
    }
}
