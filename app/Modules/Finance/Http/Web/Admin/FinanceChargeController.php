<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceChargeController extends Controller
{
    private const MANUAL_CREATE_CHARGE_TYPES = [
        FinanceCharge::TYPE_TUITION_TERM,
        FinanceCharge::TYPE_EGC_LEVEL_FEE,
        FinanceCharge::TYPE_RETAKE_FEE,
        FinanceCharge::TYPE_COURSE_FEE,
        FinanceCharge::TYPE_MANUAL_FEE,
        FinanceCharge::TYPE_ADMISSION_FEE,
        FinanceCharge::TYPE_DEFER_CREDIT,
        FinanceCharge::TYPE_EGC_EXEMPT_CREDIT,
        FinanceCharge::TYPE_ADJUSTMENT,
    ];

    public function __construct(
        private CreateFinanceChargeAction $createChargeAction,
        private VoidFinanceChargeAction $voidChargeAction,
    ) {}

    /**
     * Display a listing of finance charges.
     */
    public function index(Request $request): Response
    {
        return $this->renderIndex($request);
    }

    /**
     * Display a listing of finance charges for a specific student.
     */
    public function studentCharges(Request $request, Student $student): Response
    {
        return $this->renderIndex($request, $student);
    }

    private function renderIndex(Request $request, ?Student $student = null): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'student_id' => 'nullable|integer|exists:students,id',
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'charge_type' => 'nullable|string',
            'status' => 'nullable|string|in:all,active,void',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $selectedStudent = $student;

        if (! $selectedStudent && ! empty($validated['student_id'])) {
            $selectedStudent = Student::find($validated['student_id']);
        }

        $query = FinanceCharge::query()
            ->with(['student', 'semester', 'createdBy']);

        if ($selectedStudent) {
            $query->where('student_id', $selectedStudent->id);
        }

        if (! empty($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('description', 'like', "%{$validated['search']}%")
                    ->orWhereHas('student', function ($q) use ($validated) {
                        $q->where('full_name', 'like', "%{$validated['search']}%")
                            ->orWhere('student_id', 'like', "%{$validated['search']}%")
                            ->orWhere('email', 'like', "%{$validated['search']}%");
                    });
            });
        }

        if (! empty($validated['semester_id'])) {
            $query->where('semester_id', $validated['semester_id']);
        }

        if (! empty($validated['charge_type']) && $validated['charge_type'] !== 'all') {
            $query->where('charge_type', $validated['charge_type']);
        }

        if (! empty($validated['status']) && $validated['status'] !== 'all') {
            $query->where('status', $validated['status']);
        }

        $charges = $query->orderBy('effective_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        $semesters = Semester::orderBy('start_date', 'desc')->get();

        $chargeTypes = collect(self::MANUAL_CREATE_CHARGE_TYPES)->map(fn ($type) => [
            'value' => $type,
            'label' => ucwords(str_replace('_', ' ', $type)),
        ]);

        return Inertia::render('Finance/Charges/Index', [
            'charges' => $charges,
            'semesters' => $semesters,
            'chargeTypes' => $chargeTypes,
            'student' => $selectedStudent ? [
                'id' => $selectedStudent->id,
                'full_name' => $selectedStudent->full_name,
                'student_id' => $selectedStudent->student_id,
            ] : null,
            'filters' => [
                'search' => $validated['search'] ?? '',
                'student_id' => $selectedStudent?->id ?? $validated['student_id'] ?? null,
                'semester_id' => $validated['semester_id'] ?? null,
                'charge_type' => $validated['charge_type'] ?? 'all',
                'status' => $validated['status'] ?? 'all',
            ],
        ]);
    }

    /**
     * Display the specified charge.
     */
    public function show(FinanceCharge $charge): Response
    {
        $charge->load([
            'student',
            'semester',
            'billingCycle',
            'createdBy',
            'voidedBy',
            'invoiceLines.paymentApplications.payment',
            'invoiceLines.invoice',
        ]);

        return Inertia::render('Finance/Charges/Show', [
            'charge' => $charge,
        ]);
    }

    /**
     * Show the form for creating a new charge.
     */
    public function create(Request $request): Response
    {
        $semesters = Semester::orderBy('start_date', 'desc')->get();

        $chargeTypes = collect(FinanceCharge::CHARGE_TYPES)->map(fn ($type) => [
            'value' => $type,
            'label' => ucwords(str_replace('_', ' ', $type)),
        ]);

        $student = null;
        $draftInvoices = [];

        if ($request->has('student_id')) {
            $student = Student::find($request->get('student_id'));

            // Check if there's a semester selected
            if ($request->has('semester_id') && $student) {
                $semesterId = (int) $request->get('semester_id');

                // Find all draft invoices for this student and semester
                $draftInvoices = StudentInvoice::where('student_id', $student->id)
                    ->where('semester_id', $semesterId)
                    ->where('status', 'draft')
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(fn ($invoice) => [
                        'id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'status' => $invoice->status,
                        'created_at' => $invoice->created_at->format('Y-m-d H:i:s'),
                    ])
                    ->toArray();
            }
        }

        return Inertia::render('Finance/Charges/Create', [
            'semesters' => $semesters,
            'chargeTypes' => $chargeTypes,
            'student' => $student,
            'draftInvoices' => $draftInvoices,
        ]);
    }

    /**
     * Store a newly created charge.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'semester_id' => 'required|integer|exists:semesters,id',
            'charge_type' => 'required|string|in:'.implode(',', self::MANUAL_CREATE_CHARGE_TYPES),
            'amount' => 'required|numeric',
            'description' => 'required|string|max:500',
            'effective_at' => 'nullable|date',
            'invoice_id' => 'nullable|integer|exists:student_invoices,id',
        ]);

        try {
            $charge = $this->createChargeAction->handle($validated);

            return redirect()
                ->route('finance.charges.show', $charge)
                ->with('success', 'Charge created successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Void a charge.
     */
    public function void(Request $request, FinanceCharge $charge)
    {
        $validated = $request->validate([
            'void_reason' => 'required|string|max:500',
        ]);

        try {
            $result = $this->voidChargeAction->handle($charge->id, $validated['void_reason']);

            $message = 'Charge voided successfully.';
            if ($result['released_allocations'] > 0) {
                $message .= " Released {$result['released_allocations']} allocations"
                    .' ('.number_format($result['released_amount']).'đ).'
                    .' '.count($result['affected_payments']).' payment(s) now have unapplied balance.';
            }

            if (($result['reallocated_allocations'] ?? 0) > 0) {
                $message .= ' Auto-reallocated '
                    .$result['reallocated_allocations'].' allocation(s)'
                    .' ('.number_format($result['reallocated_amount']).'đ) to remaining unpaid charges.';
            }

            return back()->with('success', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
