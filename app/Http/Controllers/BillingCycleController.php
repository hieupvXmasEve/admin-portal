<?php

namespace App\Http\Controllers;

use App\Exports\BillingCycleInvoicesExport;
use App\Http\Requests\BillingCycleRequest;
use App\Models\BillingCycle;
use App\Models\Campus;
use App\Models\Semester;
use App\Services\BillingCycleService;
use App\Services\ExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BillingCycleController extends Controller
{
    public function __construct(
        private BillingCycleService $billingCycleService,
        private ExcelExportService $excelExportService
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
    public function show(Request $request, BillingCycle $billingCycle): Response
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:all,draft,pending,paid,overdue,cancelled',
            'campus_id' => 'nullable|integer|exists:campuses,id',
            'per_page' => 'nullable|integer|min:5|max:100',
            'search' => 'nullable|string|max:255',
        ]);

        $billingCycle->load('semester');

        $invoicesQuery = $billingCycle->invoices()
            ->with([
                'student:id,student_id,full_name,campus_id',
                'student.campus:id,name',
            ]);

        if (!empty($validated['status']) && $validated['status'] !== 'all') {
            $invoicesQuery->where('status', $validated['status']);
        }

        if (!empty($validated['campus_id'])) {
            $invoicesQuery->whereHas('student', function ($query) use ($validated) {
                $query->where('campus_id', $validated['campus_id']);
            });
        }

        if (!empty($validated['search'])) {
            $searchTerm = $validated['search'];

            $invoicesQuery->where(function ($query) use ($searchTerm) {
                $query->where('invoice_number', 'like', "%{$searchTerm}%")
                    ->orWhereHas('student', function ($studentQuery) use ($searchTerm) {
                        $studentQuery->where('student_id', 'like', "%{$searchTerm}%")
                            ->orWhere('full_name', 'like', "%{$searchTerm}%");
                    });
            });
        }

        $perPage = $validated['per_page'] ?? 10;

        $invoices = $invoicesQuery
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        $campuses = Campus::orderBy('name')->get(['id', 'name']);

        $statusOptions = [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'paid', 'label' => 'Paid'],
            ['value' => 'overdue', 'label' => 'Overdue'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
        ];

        return Inertia::render('BillingCycles/Show', [
            'billingCycle' => $billingCycle,
            'invoices' => $invoices,
            'campuses' => $campuses,
            'filters' => [
                'status' => $validated['status'] ?? 'all',
                'campus_id' => $validated['campus_id'] ?? null,
                'per_page' => $perPage,
                'search' => $validated['search'] ?? '',
            ],
            'statusOptions' => $statusOptions,
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

    /**
     * Export invoices for the specified billing cycle to Excel.
     */
    public function exportInvoices(Request $request, BillingCycle $billingCycle): BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'nullable|string|in:all,draft,pending,paid,overdue,cancelled',
                'campus_id' => 'nullable|integer|exists:campuses,id',
                'search' => 'nullable|string|max:255',
            ]);

            // Build query with same filters as show method
            $invoicesQuery = $billingCycle->invoices()
                ->with([
                    'student:id,student_id,full_name,campus_id',
                    'student.campus:id,name',
                ]);

            // Apply filters
            if (!empty($validated['status']) && $validated['status'] !== 'all') {
                $invoicesQuery->where('status', $validated['status']);
            }

            if (!empty($validated['campus_id'])) {
                $invoicesQuery->whereHas('student', function ($query) use ($validated) {
                    $query->where('campus_id', $validated['campus_id']);
                });
            }

            if (!empty($validated['search'])) {
                $searchTerm = $validated['search'];

                $invoicesQuery->where(function ($query) use ($searchTerm) {
                    $query->where('invoice_number', 'like', "%{$searchTerm}%")
                        ->orWhereHas('student', function ($studentQuery) use ($searchTerm) {
                            $studentQuery->where('student_id', 'like', "%{$searchTerm}%")
                                ->orWhere('full_name', 'like', "%{$searchTerm}%");
                        });
                });
            }

            $invoices = $invoicesQuery
                ->orderByDesc('created_at')
                ->get();

            // Create export instance
            $export = new BillingCycleInvoicesExport(
                $invoices,
                $billingCycle->name,
                $validated
            );

            // Generate filename with timestamp
            $filename = $this->excelExportService->generateFilenameWithTimestamp(
                "billing_cycle_{$billingCycle->id}_invoices"
            );

            return $this->excelExportService->download($export, $filename, 'xlsx');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Export invoices error', [
                'billing_cycle_id' => $billingCycle->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to export invoices: ' . $e->getMessage(),
            ], 500);
        }
    }
}
