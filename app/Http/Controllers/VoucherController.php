<?php

namespace App\Http\Controllers;

use App\Models\VoucherDefinition;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class VoucherController extends Controller
{
    public function __construct(
        protected VoucherService $voucherService
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

        $vouchers = $query->withCount('redemptions')
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
        $voucher->load(['redemptions.student', 'redemptions.invoice']);

        return Inertia::render('Vouchers/Show', [
            'voucher' => $voucher,
            'redemptions' => $this->voucherService->getVoucherRedemptions($voucher->id),
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
            'code' => 'required|string|max:50|unique:voucher_definitions,code,' . $voucher->id,
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
    public function redeem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'code' => 'required|string|exists:voucher_definitions,code',
            'invoice_id' => 'nullable|exists:student_invoices,id',
        ]);

        try {
            $this->voucherService->redeemVoucher(
                $validated['student_id'],
                $validated['code'],
                $validated['invoice_id'] ?? null
            );

            return redirect()->back()
                ->with('success', 'Voucher redeemed successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }
}
