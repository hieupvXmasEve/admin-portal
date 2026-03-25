<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\AutoAllocatePaymentsAction;
use App\Modules\Finance\Queries\Operations\ListSettlementWorklistQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingSettlementController extends Controller
{
    public function index(Request $request, ListSettlementWorklistQuery $query): Response
    {
        return Inertia::render('Finance/Operations/Settlement', $query->handle($request));
    }

    public function apply(Request $request, AutoAllocatePaymentsAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'priority_order' => 'required|array',
            'priority_order.*' => 'string',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'integer|exists:students,id',
        ]);

        $studentIds = $validated['student_ids'] ?? [];

        $stats = $studentIds === []
            ? $action->run($validated['priority_order'], $request->user()?->id)
            : $action->runForStudents($studentIds, $validated['priority_order'], $request->user()?->id);

        $message = "Settlement complete. Processed {$stats['students_processed']} students, created {$stats['allocations_created']} payment applications.";

        return redirect()
            ->route('finance.operations.settlement.index')
            ->with('success', $message);
    }
}
