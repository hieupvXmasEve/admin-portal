<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sets the operator's selected semester (single source for semester-bound finance
 * surfaces). Stores only in session; reflected app-wide via the shared `semester`
 * Inertia prop. No money state.
 */
class FinanceSemesterContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
        ]);

        session(['current_semester_id' => $validated['semester_id'] ?? null]);

        return back();
    }
}
