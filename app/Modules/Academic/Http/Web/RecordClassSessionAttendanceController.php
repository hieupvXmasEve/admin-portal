<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Modules\Academic\Actions\Attendance\RecordAttendanceAction;
use App\Modules\Academic\Http\Requests\Attendance\RecordClassSessionAttendanceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

/**
 * Cockpit "record attendance" action (ADR 0013 phase B): records a batch of
 * per-student attendance for one session from the cockpit sessions view,
 * instead of the standalone staff Attendance pages. Reuses
 * RecordAttendanceAction so the recording logic never drifts from
 * AttendanceController::store. The frontend follows the redirect with an
 * Inertia partial reload of `operational_state` (and `courseOffering`) so the
 * per-session attendance status and readiness blockers both refresh.
 *
 * Campus/parent-child scoping is enforced by the FormRequest's authorize()
 * (mirrors FinalizeCourseOfferingController's pattern of keeping that check
 * out of the controller); this stays a thin validate → Action → redirect flow.
 */
class RecordClassSessionAttendanceController extends Controller
{
    public function __invoke(RecordClassSessionAttendanceRequest $request, CourseOffering $courseOffering, ClassSession $classSession): RedirectResponse
    {
        $records = $request->validated('records');

        foreach ($records as $record) {
            RecordAttendanceAction::run([
                'class_session_id' => $classSession->id,
                'student_id' => $record['student_id'],
                'status' => $record['status'],
                'notes' => $record['notes'] ?? null,
                'recording_method' => 'manual',
            ]);
        }

        Inertia::flash('message', 'Attendance recorded for '.count($records).' student(s).');

        return Redirect::back();
    }
}
