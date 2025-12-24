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

        $query = FormTarget::query()
            ->with(['form', 'formVersion', 'campus'])
            ->latest('start_at');

        if ($request->filled('scope_type') && $request->input('scope_type') !== 'all') {
            $query->where('scope_type', $request->input('scope_type'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $runs = $query->paginate(15)->withQueryString();

        return Inertia::render('Forms/Runs/Index', [
            'runs' => $runs,
            'filters' => $request->only(['scope_type', 'status']),
        ]);
    }

    public function create()
    {
        $this->authorize('edit_form');
        return Inertia::render('Forms/Runs/Create', [
            'forms' => Form::select('id', 'title')->get(),
            'semesters' => Semester::latest()->take(5)->get(), // For context
            'departments' => Department::all(), // For department context
        ]);
    }

    public function store(Request $request, CreateFormTargetAction $action)
    {
        $this->authorize('edit_form');
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

        // Additional validation
        if ($validated['scope_type'] === 'semester') {
            $validated['scope_id'] = $validated['semester_id'];
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
