<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Modules\Academic\Delivery\Actions\BulkUpdateClassSessionAttendanceAction;
use App\Modules\Academic\Http\Requests\Attendance\BulkUpdateClassSessionAttendanceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class BulkUpdateClassSessionAttendanceController extends Controller
{
    public function __invoke(BulkUpdateClassSessionAttendanceRequest $request, ClassSession $classSession): RedirectResponse
    {
        $updated = BulkUpdateClassSessionAttendanceAction::run([
            'class_session' => $classSession,
            'attendance_ids' => $request->validated('attendance_ids'),
            'status' => $request->validated('status'),
        ]);

        Inertia::flash('message', "Updated {$updated} attendance record(s).");

        return Redirect::back();
    }
}
