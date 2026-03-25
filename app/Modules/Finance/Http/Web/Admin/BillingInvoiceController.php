<?php

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceLine;
use App\Models\StudentInvoice;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Http\Export\InvoiceExport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class BillingInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = StudentInvoice::query()
            ->with(['student', 'semester'])
            ->latest();

        // Filter by Campus
        if ($campusId = app('campus')?->id) {
            $query->forCampus($campusId);
        }

        // Filter by Semester
        if ($request->filled('semester_id')) {
            $query->forSemester($request->semester_id);
        }

        // Filter by Search (Student Name, ID, Invoice Number)
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Filter by Status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->filterByStatus($request->status);
        }

        $invoices = $query->paginate($request->input('per_page', 50))
            ->withQueryString();

        // Transform collection to append real-time status if not already appends in model
        // We can do this via API Resource or just append here if it's not heavy.
        $invoices->getCollection()->transform(function ($invoice) {
            $invoice->append(['real_time_status', 'total_amount', 'paid_amount', 'outstanding_balance']);

            return $invoice;
        });

        return Inertia::render('Finance/Invoices/Index', [
            'invoices' => $invoices,
            'filters' => $request->only(['search', 'semester_id', 'status', 'per_page']),
        ]);
    }

    public function show(StudentInvoice $invoice)
    {
        // Ensure campus check
        if ($campusId = app('campus')?->id) {
            if ($invoice->student->campus_id !== $campusId) {
                abort(403, 'This invoice does not belong to your campus.');
            }
        }

        $invoice->load([
            'student.program',
            'semester',
            'invoiceLines.charge', // Load charge details
            'billingCycle',
        ]);

        // Eager load payments via charges -> allocations
        // Since we don't have a direct relation from Invoice to Allocations easily without Deep nesting,
        // We might want to fetch allocations derived from charges.
        // The accessor 'charges' on Invoice model uses hasManyThrough.
        // Let's load charges and their allocations.
        $invoice->charges->load('invoiceLines.paymentApplications.payment', 'source', 'createdBy');
        $invoice->charges->each->append(['paid_amount', 'balance']);

        $invoice->append(['real_time_status', 'total_amount', 'paid_amount', 'outstanding_balance']);

        return Inertia::render('Finance/Invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    public function voidLine(Request $request, StudentInvoice $invoice, InvoiceLine $line, VoidFinanceChargeAction $action)
    {
        if ((int) $line->invoice_id !== (int) $invoice->id) {
            abort(404);
        }

        $validated = $request->validate([
            'void_reason' => 'required|string|max:500',
        ]);

        if (! $line->charge_id) {
            return back()->withErrors(['error' => 'Invoice line is not linked to a finance charge.']);
        }

        $action->handle((int) $line->charge_id, $validated['void_reason'], $request->user()?->id);

        return back()->with('success', 'Invoice line voided successfully.');
    }

    public function export(Request $request)
    {
        // Re-use logic or minimal logic for export
        // For now, simple export placeholder or real implementation if simple.
        // We need an Export class. I will stub this out or skip if not in immediate scope of a simple file writer.
        // The requirement says "Export to Excel". I should probably create an export class.

        return Excel::download(new InvoiceExport($request->all()), 'invoices.xlsx');
    }
}
