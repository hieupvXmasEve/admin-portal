<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\Egc\ApplyEgcMajorEntryCreditAction;
use App\Modules\Finance\Actions\Egc\ApplyEgcRetakeDiscountAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EgcRetakeAdjustmentsController extends Controller
{
    public function index(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
        ]);

        return redirect()->route('finance.egc.block-results.index', array_filter([
            'semester_id' => $validated['semester_id'] ?? null,
            'section' => 'retake-adjustments',
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'egc_block_id' => 'required|integer|exists:egc_blocks,id',
            'target_charge_id' => 'required|integer|exists:finance_charges,id',
        ]);

        ApplyEgcRetakeDiscountAction::run(
            (int) $validated['egc_block_id'],
            (int) $validated['target_charge_id']
        );

        Inertia::flash('success', 'Retake discount applied successfully.');

        return redirect()->back();
    }

    public function applyMajorEntryCredit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'semester_id' => 'required|integer|exists:semesters,id',
        ]);

        ApplyEgcMajorEntryCreditAction::run(
            (int) $validated['student_id'],
            (int) $validated['semester_id']
        );

        Inertia::flash('success', 'Major entry credit applied successfully.');

        return redirect()->back();
    }
}
