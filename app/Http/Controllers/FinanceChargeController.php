<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Services\FinanceChargeService;
use App\Modules\Finance\Services\PaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceChargeController extends Controller
{
    public function __construct(
        private FinanceChargeService $chargeService,
        private PaymentService $paymentService
    ) {}

    /**
     * Display a listing of finance charges.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'charge_type' => 'nullable|string',
            'status' => 'nullable|string|in:all,active,void',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = FinanceCharge::query()
            ->with(['student', 'semester', 'createdBy']);

        // Apply search filter
        if (!empty($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('description', 'like', "%{$validated['search']}%")
                    ->orWhereHas('student', function ($q) use ($validated) {
                        $q->where('full_name', 'like', "%{$validated['search']}%")
                            ->orWhere('student_id', 'like', "%{$validated['search']}%")
                            ->orWhere('email', 'like', "%{$validated['search']}%");
                    });
            });
        }

        // Apply semester filter
        if (!empty($validated['semester_id'])) {
            $query->where('semester_id', $validated['semester_id']);
        }

        // Apply charge type filter
        if (!empty($validated['charge_type']) && $validated['charge_type'] !== 'all') {
            $query->where('charge_type', $validated['charge_type']);
        }

        // Apply status filter
        if (!empty($validated['status']) && $validated['status'] !== 'all') {
            $query->where('status', $validated['status']);
        }

        $charges = $query->orderBy('effective_at', 'desc')
            ->paginate($validated['per_page'] ?? 20);

        // Get semesters for filters
        $semesters = Semester::orderBy('start_date', 'desc')->get();

        // Get charge type options
        $chargeTypes = collect(FinanceCharge::CHARGE_TYPES)->map(fn($type) => [
            'value' => $type,
            'label' => ucwords(str_replace('_', ' ', $type)),
        ]);

        return Inertia::render('Finance/Charges/Index', [
            'charges' => $charges,
            'semesters' => $semesters,
            'chargeTypes' => $chargeTypes,
            'filters' => [
                'search' => $validated['search'] ?? '',
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
            'allocations.payment',
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
        
        $chargeTypes = collect(FinanceCharge::CHARGE_TYPES)->map(fn($type) => [
            'value' => $type,
            'label' => ucwords(str_replace('_', ' ', $type)),
        ]);

        $student = null;
        if ($request->has('student_id')) {
            $student = Student::find($request->get('student_id'));
        }

        return Inertia::render('Finance/Charges/Create', [
            'semesters' => $semesters,
            'chargeTypes' => $chargeTypes,
            'student' => $student,
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
            'charge_type' => 'required|string|in:' . implode(',', FinanceCharge::CHARGE_TYPES),
            'amount' => 'required|numeric',
            'description' => 'required|string|max:500',
            'effective_at' => 'nullable|date',
        ]);

        try {
            $charge = $this->chargeService->createCharge($validated);

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
            $this->chargeService->voidCharge($charge->id, $validated['void_reason']);

            return back()->with('success', 'Charge voided successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display student charges summary.
     */
    public function studentCharges(Student $student, Request $request): Response
    {
        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ]);

        $semesterId = $validated['semester_id'] ?? null;

        $charges = $this->chargeService->getStudentCharges($student->id, $semesterId);
        $balance = $this->paymentService->getStudentBalance($student->id, $semesterId);

        $semesters = Semester::whereIn('id', 
            FinanceCharge::where('student_id', $student->id)
                ->distinct()
                ->pluck('semester_id')
        )->orderBy('start_date', 'desc')->get();

        return Inertia::render('Finance/StudentCharges', [
            'student' => $student->load('campus', 'program'),
            'charges' => $charges,
            'balance' => $balance,
            'semesters' => $semesters,
            'filters' => [
                'semester_id' => $semesterId,
            ],
        ]);
    }
}
