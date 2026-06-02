<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Form\ActivateFormTargetAction;
use App\Actions\Form\CreateFormTargetAction;
use App\Http\Controllers\Controller;
use App\Models\FormTarget;
use App\Models\Form;
use App\Models\Semester;
use App\Models\CourseOffering;
use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FormTargetController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view_form');

        $validated = $request->validate([
            'scope_type' => 'nullable|string|in:course,semester,department,global,all',
            'status' => 'nullable|string|in:draft,active,closed,all',
            'created_from' => 'nullable|date',
            'created_to' => 'nullable|date|after_or_equal:created_from',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = FormTarget::query()
            ->forCurrentCampus()
            ->with([
                'form:id,code,title,type,status',
                'formVersion', 
                'campus', 
                'semester',
                'scope' => function ($morphTo) {
                    $morphTo->morphWith([
                        \App\Models\CourseOffering::class => ['unit'],
                    ]);
                }
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
            'semesters' => Semester::query()
                ->orderBy('start_date')
                ->orderBy('id')
                ->get(['id', 'name', 'code', 'start_date', 'end_date']),
            'departments' => Department::all(), // For department context
        ]);
    }

    public function store(Request $request, CreateFormTargetAction $action)
    {
        $this->authorize('edit_form');
        $request->merge([
            'scope_type' => 'global',
            'scope_id' => null,
            'semester_id' => null,
        ]);

        $validated = $request->validate([
            'form_id' => 'required|exists:forms,id',
            'form_version_id' => 'nullable|exists:form_versions,id',
            'scope_type' => 'required|in:course,semester,department,global',
            'scope_id' => 'nullable', // Validation depends on type
            'semester_id' => 'nullable|required_if:scope_type,semester',
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after:start_at',
            'is_mandatory' => 'boolean',
        ]);

        if ($campusId = session('current_campus_id')) {
            $validated['campus_id'] = $campusId;
        }

        $action->execute($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Form Run created successfully.'
            ]);
        }

        return redirect()->route('forms.admin.runs.index')->with('success', 'Form Run created successfully.');
    }

    public function activate(FormTarget $target, ActivateFormTargetAction $action)
    {
        $this->authorize('edit_form');
        
        $action->execute($target);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Run activated.'
            ]);
        }

        return back()->with('success', 'Run activated.');
    }

    public function close(FormTarget $target)
    {
        $this->authorize('edit_form');
        $target->update(['status' => 'closed']);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Run closed.'
            ]);
        }

        return back()->with('success', 'Run closed.');
    }
}
