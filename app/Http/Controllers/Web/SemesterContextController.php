<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\SemesterContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SemesterContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $semesterId = $request->input('semester_id');

        if ($semesterId === null || $semesterId === '' || $semesterId === SemesterContextResolver::AllSemesters) {
            session([SemesterContextResolver::SessionKey => SemesterContextResolver::AllSemesters]);

            return back();
        }

        $validated = $request->validate([
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
        ]);

        session([SemesterContextResolver::SessionKey => (int) $validated['semester_id']]);

        return back();
    }
}
