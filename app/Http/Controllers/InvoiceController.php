<?php

namespace App\Http\Controllers;

use App\Models\BillingCycle;
use App\Models\StudentInvoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoiceService
    ) {}

    /**
     * Display a listing of invoices.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'billing_cycle_id' => 'nullable|integer|exists:billing_cycles,id',
            'status' => 'nullable|string|in:all,draft,pending,paid,overdue,cancelled',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = StudentInvoice::query()
            ->with(['student', 'billingCycle', 'semester']);

        // Apply search filter
        if (! empty($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('invoice_number', 'like', "%{$validated['search']}%")
                    ->orWhereHas('student', function ($q) use ($validated) {
                        $q->where('full_name', 'like', "%{$validated['search']}%")
                            ->orWhere('student_id', 'like', "%{$validated['search']}%")
                            ->orWhere('email', 'like', "%{$validated['search']}%");
                    });
            });
        }

        // Apply billing cycle filter
        if (! empty($validated['billing_cycle_id'])) {
            $query->where('billing_cycle_id', $validated['billing_cycle_id']);
        }

        // Apply status filter (ignore 'all')
        if (! empty($validated['status']) && $validated['status'] !== 'all') {
            $query->where('status', $validated['status']);
        }

        $invoices = $query->orderBy('created_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        // Get billing cycles for filters
        $billingCycles = BillingCycle::with('semester')
            ->orderBy('start_date', 'desc')
            ->get();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'billingCycles' => $billingCycles,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'billing_cycle_id' => $validated['billing_cycle_id'] ?? null,
                'status' => $validated['status'] ?? 'all',
            ],
        ]);
    }

    /**
     * Display the specified invoice.
     */
    public function show(StudentInvoice $invoice): Response
    {
        $invoice->load([
            'student',
            'billingCycle.semester',
            'semester',
            'items',
            'discounts' => function ($query) {
                $query->with(['voucher:id,code,name,discount_type,discount_value', 'scholarship:id,code,name,type,amount']);
            },
        ]);

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Show the form for generating invoices.
     */
    public function create(): Response
    {
        $billingCycles = BillingCycle::with('semester')
            ->where('status', 'active')
            ->orderBy('start_date', 'desc')
            ->get();

        return Inertia::render('Invoices/Generate', [
            'billingCycles' => $billingCycles,
        ]);
    }

    /**
     * Generate invoices for a billing cycle.
     */
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'billing_cycle_id' => 'required|integer|exists:billing_cycles,id',
        ]);

        try {
            $result = $this->invoiceService->generateInvoicesForCycle($validated['billing_cycle_id']);
            $stats = $result['stats'];

            // Build success message
            $message = 'Invoice generation completed: ';
            $details = [];

            if ($stats['created'] > 0) {
                $details[] = "{$stats['created']} created";
            }

            if ($stats['updated'] > 0) {
                $details[] = "{$stats['updated']} updated";
            }

            $message .= implode(', ', $details);
            $message .= " (Total: {$stats['total']})";

            return redirect()->route('invoices.index', ['billing_cycle_id' => $validated['billing_cycle_id']])
                ->with('success', $message)
                ->with('success_details', $stats);
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Add an item to an invoice.
     */
    public function addItem(Request $request, StudentInvoice $invoice)
    {
        $validated = $request->validate([
            'item_type' => 'required|string|in:tuition,egc,retake,miscellaneous',
            'description' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $this->invoiceService->addInvoiceItem($invoice->id, $validated);

            return back()->with('success', 'Invoice item added successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Apply a discount to an invoice.
     */
    public function applyDiscount(Request $request, StudentInvoice $invoice)
    {
        $validated = $request->validate([
            'voucher_id' => 'required|integer|exists:voucher_definitions,id',
        ]);

        try {
            $this->invoiceService->applyVoucherDiscount($invoice->id, $validated['voucher_id']);

            return back()->with('success', 'Discount applied successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
