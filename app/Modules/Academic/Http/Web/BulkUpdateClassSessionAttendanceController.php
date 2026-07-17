<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Modules\Academic\Actions\Attendance\BulkUpdateClassSessionAttendanceAction;
use App\Modules\Academic\Http\Requests\Attendance\BulkUpdateClassSessionAttendanceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class BulkUpdateClassSessionAttendanceController extends Controller
{
    public function __invoke(BulkUpdateClassSessionAttendanceRequest $request, ClassSession $classSession): RedirectResponse
    {
        $updated = BulkUpdateClassSessionAttendanceAction::run(
            $classSession,
            $request->validated('attendance_ids'),
            $request->validated('status'),
        );

        Inertia::flash('message', "Updated {$updated} attendance record(s).");

        return Redirect::back();
    }
}
