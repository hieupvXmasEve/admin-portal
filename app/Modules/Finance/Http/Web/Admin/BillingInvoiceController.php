<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiscountAllocation;
use App\Models\InvoiceLine;
use App\Models\PaymentApplication;
use App\Models\StudentInvoice;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Modules\Finance\Http\Export\InvoiceExport;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class BillingInvoiceController extends Controller
{
    public function __construct(
        private readonly SettlementService $settlementService,
    ) {}

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
            'invoiceLines.charge',
            'invoiceLines.paymentApplications.payment',
            'invoiceLines.discountAllocations.invoiceDiscount',
            'billingCycle',
        ]);

        $snapshot = $this->settlementService->deriveInvoiceSnapshot($invoice);
        $activeLines = $invoice->invoiceLines
            ->filter(fn (InvoiceLine $line) => ($line->status ?? 'active') === 'active')
            ->values();

        return Inertia::render('Finance/Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'student' => [
                    'id' => $invoice->student->id,
                    'full_name' => $invoice->student->full_name,
                    'student_id' => $invoice->student->student_id,
                    'email' => $invoice->student->email,
                    'program' => $invoice->student->program ? [
                        'name' => $invoice->student->program->name,
                    ] : null,
                ],
                'semester' => [
                    'id' => $invoice->semester->id,
                    'name' => $invoice->semester->name,
                ],
                'billing_cycle' => $invoice->billingCycle ? [
                    'name' => $invoice->billingCycle->name,
                ] : null,
                'due_date' => $invoice->due_date?->toIso8601String(),
                'created_at' => $invoice->created_at?->toIso8601String(),
                'real_time_status' => $invoice->real_time_status,
                'subtotal' => (float) $snapshot['gross'],
                'discount_total' => (float) $snapshot['discount'],
                'total_amount' => (float) $snapshot['net'],
                'paid_amount' => (float) $snapshot['paid'],
                'outstanding_balance' => (float) $snapshot['remaining'],
                'charges' => $this->mapCharges($activeLines),
                'settlement_entries' => $this->mapSettlementEntries($activeLines),
            ],
        ]);
    }

    private function mapCharges(Collection $lines): array
    {
        return $lines
            ->filter(fn (InvoiceLine $line) => $line->charge !== null)
            ->map(function (InvoiceLine $line): array {
                $paidAmount = max(0, (float) $line->paymentApplications->sum('amount'));
                $discountAmount = max(0, (float) $line->discountAllocations->sum('amount'));
                $amount = (float) $line->amount_snapshot;

                return [
                    'id' => $line->charge->id,
                    'invoice_line_id' => $line->id,
                    'charge_type' => $line->charge->charge_type,
                    'description' => $line->description_snapshot ?: $line->charge->description,
                    'amount' => $amount,
                    'paid_amount' => $paidAmount,
                    'discount_amount' => $discountAmount,
                    'settled_amount' => min($amount, $paidAmount + $discountAmount),
                    'balance' => max(0, $amount - $paidAmount - $discountAmount),
                    'effective_at' => $line->charge->effective_at?->toIso8601String(),
                    'source_type' => $line->charge->source_type,
                    'status' => $line->charge->status,
                ];
            })
            ->values()
            ->all();
    }

    private function mapSettlementEntries(Collection $lines): array
    {
        $paymentEntries = $lines->flatMap(function (InvoiceLine $line): Collection {
            return $line->paymentApplications->map(function (PaymentApplication $application) use ($line): array {
                return [
                    'id' => "payment-{$application->id}",
                    'entry_group' => 'payment',
                    'entry_type' => $application->entry_type,
                    'applied_at' => $application->applied_at?->toIso8601String(),
                    'amount' => (float) $application->amount,
                    'charge' => [
                        'id' => $line->charge?->id,
                        'description' => $line->description_snapshot,
                        'charge_type' => $line->charge?->charge_type,
                    ],
                    'payment' => $application->payment ? [
                        'id' => $application->payment->id,
                        'amount' => (float) $application->payment->amount,
                        'paid_at' => $application->payment->paid_at?->toIso8601String(),
                        'method' => $application->payment->method,
                        'status' => $application->payment->status,
                        'external_ref' => $application->payment->external_ref,
                    ] : null,
                    'discount' => null,
                ];
            });
        });

        $discountEntries = $lines->flatMap(function (InvoiceLine $line): Collection {
            return $line->discountAllocations->map(function (DiscountAllocation $allocation) use ($line): array {
                return [
                    'id' => "discount-{$allocation->id}",
                    'entry_group' => 'discount',
                    'entry_type' => $allocation->entry_type,
                    'applied_at' => $allocation->created_at?->toIso8601String(),
                    'amount' => (float) $allocation->amount,
                    'charge' => [
                        'id' => $line->charge?->id,
                        'description' => $line->description_snapshot,
                        'charge_type' => $line->charge?->charge_type,
                    ],
                    'payment' => null,
                    'discount' => $allocation->invoiceDiscount ? [
                        'id' => $allocation->invoiceDiscount->id,
                        'description' => $allocation->invoiceDiscount->description,
                        'discount_type' => $allocation->invoiceDiscount->discount_type,
                        'discount_source' => $allocation->invoiceDiscount->discount_source,
                    ] : null,
                ];
            });
        });

        return $paymentEntries
            ->concat($discountEntries)
            ->sortByDesc(fn (array $entry) => $entry['applied_at'] ?? '')
            ->values()
            ->all();
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
