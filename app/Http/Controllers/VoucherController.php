<?php

namespace App\Http\Controllers;

use App\Http\Requests\RedeemVoucherRequest;
use App\Http\Requests\VoucherImportRequest;
use App\Models\BillingCycle;
use App\Models\Semester;
use App\Models\VoucherDefinition;
use App\Services\VoucherImportService;
use App\Services\VoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VoucherController extends Controller
{
    public function __construct(
        protected VoucherService $voucherService,
        protected VoucherImportService $importService
    ) {}

    /**
     * Display a listing of vouchers
     */
    public function index(Request $request): Response
    {
        $query = VoucherDefinition::query();

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Apply type filter
        if ($request->filled('type')) {
            $query->where('voucher_type', $request->type);
        }

        // Apply status filter
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true)
                    ->where('valid_from', '<=', now())
                    ->where('valid_until', '>=', now());
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($request->status === 'expired') {
                $query->where('valid_until', '<', now());
            }
        }

        $vouchers = $query->withCount('applications as usage_count')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Vouchers/Index', [
            'vouchers' => $vouchers,
            'filters' => $request->only(['search', 'type', 'status']),
        ]);
    }

    /**
     * Show the form for creating a new voucher
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Vouchers/Create', [
        ]);
    }

    /**
     * Store a newly created voucher
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:voucher_definitions,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'voucher_type' => 'required|in:informational,discount',
            'discount_type' => 'nullable|required_if:voucher_type,discount|in:percentage,fixed_amount',
            'discount_value' => 'nullable|required_if:voucher_type,discount|numeric|min:0',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
        ]);

        $this->voucherService->createVoucher($validated);

        return redirect()->route('vouchers.index')
            ->with('success', 'Voucher created successfully.');
    }

    /**
     * Display the specified voucher
     */
    public function show(Request $request, VoucherDefinition $voucher): Response
    {
        $currentSemester = Semester::query()
            ->where('is_active', true)
            ->first(['id', 'code', 'name']);

        return Inertia::render('Vouchers/Show', [
            'voucher' => $voucher,
            'applications' => $this->voucherService->getVoucherApplications($voucher->id),
            'currentSemester' => $currentSemester,
            'canApplyVoucher' => (bool) $request->user()?->can('edit_voucher'),
        ]);
    }

    /**
     * Show the form for editing the specified voucher
     */
    public function edit(Request $request, VoucherDefinition $voucher): Response
    {
        return Inertia::render('Vouchers/Edit', [
            'voucher' => $voucher,
        ]);
    }

    /**
     * Update the specified voucher
     */
    public function update(Request $request, VoucherDefinition $voucher): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:voucher_definitions,code,'.$voucher->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'voucher_type' => 'required|in:informational,discount',
            'discount_type' => 'nullable|required_if:voucher_type,discount|in:percentage,fixed_amount',
            'discount_value' => 'nullable|required_if:voucher_type,discount|numeric|min:0',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
        ]);

        $this->voucherService->updateVoucher($voucher->id, $validated);

        return redirect()->route('vouchers.show', $voucher)
            ->with('success', 'Voucher updated successfully.');
    }

    /**
     * Remove the specified voucher
     */
    public function destroy(VoucherDefinition $voucher): RedirectResponse
    {
        try {
            $this->voucherService->deleteVoucher($voucher->id);

            return redirect()->route('vouchers.index')
                ->with('success', 'Voucher deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Redeem a voucher for a student
     */
    public function redeem(RedeemVoucherRequest $request): RedirectResponse
    {
        try {
            $this->voucherService->redeemVoucher(
                (int) $request->integer('student_id'),
                (int) $request->integer('voucher_id'),
                $request->user()?->id,
            );

            return redirect()->back()
                ->with('success', 'Voucher applied successfully.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Show the voucher import form
     */
    public function showImport(): Response
    {
        $billingCycles = BillingCycle::with('semester')
            ->orderBy('start_date', 'desc')
            ->get();

        return Inertia::render('Vouchers/Import', [
            'billingCycles' => $billingCycles,
        ]);
    }

    /**
     * Upload and preview voucher import
     */
    public function uploadImport(VoucherImportRequest $request): Response
    {
        try {
            $file = $request->file('file');
            $billingCycleId = $request->input('billing_cycle_id');

            $preview = $this->importService->previewImport($file, $billingCycleId);

            $billingCycles = BillingCycle::with('semester')
                ->orderBy('start_date', 'desc')
                ->get();

            return Inertia::render('Vouchers/Import', [
                'billingCycles' => $billingCycles,
                'preview' => $preview,
            ]);
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Process voucher import
     */
    public function processImport(VoucherImportRequest $request): Response
    {
        try {
            $file = $request->file('file');
            $billingCycleId = $request->input('billing_cycle_id');

            $result = $this->importService->processImport($file, $billingCycleId);

            $billingCycles = BillingCycle::with('semester')
                ->orderBy('start_date', 'desc')
                ->get();

            return Inertia::render('Vouchers/Import', [
                'billingCycles' => $billingCycles,
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }
}
