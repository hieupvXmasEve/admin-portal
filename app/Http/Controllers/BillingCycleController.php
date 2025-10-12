<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillingCycleRequest;
use App\Models\BillingCycle;
use App\Models\Semester;
use App\Services\BillingCycleService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingCycleController extends Controller
{
    public function __construct(
        private BillingCycleService $billingCycleService
    ) {}

    /**
     * Display a listing of billing cycles.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'status' => 'nullable|string|in:all,draft,active,closed',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = BillingCycle::query()
            ->with(['semester'])
            ->withCount('invoices');

        // Apply search filter
        if (!empty($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('name', 'like', "%{$validated['search']}%")
                    ->orWhereHas('semester', function ($q) use ($validated) {
                        $q->where('name', 'like', "%{$validated['search']}%");
                    });
            });
        }

        // Apply semester filter
        if (!empty($validated['semester_id'])) {
            $query->where('semester_id', $validated['semester_id']);
        }

        // Apply status filter (ignore 'all')
        if (!empty($validated['status']) && $validated['status'] !== 'all') {
            $query->where('status', $validated['status']);
        }

        $billingCycles = $query->orderBy('start_date', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        // Get semesters for filters
        $semesters = Semester::orderBy('start_date', 'desc')
            ->get();

        return Inertia::render('BillingCycles/Index', [
            'billingCycles' => $billingCycles,
            'semesters' => $semesters,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'semester_id' => $validated['semester_id'] ?? null,
                'status' => $validated['status'] ?? 'all',
            ],
        ]);
    }

    /**
     * Show the form for creating a new billing cycle.
     */
    public function create(): Response
    {
        $semesters = Semester::orderBy('start_date', 'desc')
            ->get();

        return Inertia::render('BillingCycles/Create', [
            'semesters' => $semesters,
        ]);
    }

    /**
     * Store a newly created billing cycle.
     */
    public function store(BillingCycleRequest $request)
    {
        try {
            $billingCycle = $this->billingCycleService->createBillingCycle($request->validated());

            return redirect()->route('billing-cycles.show', $billingCycle)
                ->with('success', 'Billing cycle created successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified billing cycle.
     */
    public function show(BillingCycle $billingCycle): Response
    {
        $billingCycle->load([
            'semester',
            'invoices.student'
        ]);

        return Inertia::render('BillingCycles/Show', [
            'billingCycle' => $billingCycle,
        ]);
    }

    /**
     * Show the form for editing the specified billing cycle.
     */
    public function edit(BillingCycle $billingCycle): Response
    {
        $semesters = Semester::orderBy('start_date', 'desc')
            ->get();

        return Inertia::render('BillingCycles/Edit', [
            'billingCycle' => $billingCycle,
            'semesters' => $semesters,
        ]);
    }

    /**
     * Update the specified billing cycle.
     */
    public function update(BillingCycleRequest $request, BillingCycle $billingCycle)
    {
        try {
            $this->billingCycleService->updateBillingCycle($billingCycle->id, $request->validated());

            return redirect()->route('billing-cycles.show', $billingCycle)
                ->with('success', 'Billing cycle updated successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified billing cycle.
     */
    public function destroy(BillingCycle $billingCycle)
    {
        try {
            $this->billingCycleService->deleteBillingCycle($billingCycle->id);

            return redirect()->route('billing-cycles.index')
                ->with('success', 'Billing cycle deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Activate the specified billing cycle.
     */
    public function activate(BillingCycle $billingCycle)
    {
        try {
            $this->billingCycleService->activateBillingCycle($billingCycle->id);

            return redirect()->route('billing-cycles.show', $billingCycle)
                ->with('success', 'Billing cycle activated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Close the specified billing cycle.
     */
    public function close(BillingCycle $billingCycle)
    {
        try {
            $this->billingCycleService->closeBillingCycle($billingCycle->id);

            return redirect()->route('billing-cycles.show', $billingCycle)
                ->with('success', 'Billing cycle closed successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
