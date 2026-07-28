<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\AcademicWarningSetting;
use App\Models\CourseOffering;
use App\Models\GpaCalculation;
use App\Models\Student;
use App\Modules\Academic\Progression\Actions\Warnings\SendAcademicStandingWarningAction;
use App\Modules\Academic\Progression\Actions\Warnings\UpdateWarningSettingsAction;
use App\Modules\Academic\Delivery\Actions\SendAttendanceWarningAction;
use App\Modules\Academic\Delivery\Http\Requests\Warnings\SendAttendanceWarningRequest;
use App\Modules\Academic\Progression\Http\Requests\Warnings\ListWarningCenterRequest;
use App\Modules\Academic\Progression\Http\Requests\Warnings\SendAcademicStandingWarningRequest;
use App\Modules\Academic\Progression\Http\Requests\Warnings\UpdateWarningSettingsRequest;
use App\Modules\Academic\Progression\Queries\ListWarningCenterQuery;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WarningCenterController extends Controller
{
    public function __construct(
        private readonly ListWarningCenterQuery $warningCenterQuery,
        private readonly AcademicPeriodReader $academicPeriods,
    ) {}

    public function index(ListWarningCenterRequest $request): Response
    {
        $filters = $request->validated();
        $campusId = $this->currentCampusId();
        $settings = AcademicWarningSetting::forCampus($campusId);
        $currentPeriod = $this->academicPeriods->current();

        return Inertia::render('Academic/Warnings/Index', [
            'academic_warnings' => $this->warningCenterQuery->academicStandingWarnings($filters, $campusId),
            'attendance_subjects' => $this->warningCenterQuery->attendanceWarnings(
                $campusId,
                $currentPeriod?->id,
                (float) $settings->attendance_warning_ratio,
            ),
            'active_semester' => $currentPeriod ? [
                'id' => $currentPeriod->id,
                'name' => $currentPeriod->name,
                'code' => $currentPeriod->code,
            ] : null,
            'settings' => $this->settingsPayload($settings),
            'filters' => $request->only(['search', 'sort', 'direction', 'per_page']),
        ]);
    }

    public function settings(Request $request): Response
    {
        abort_unless($request->user()?->can('send_manual_notification'), 403);

        return Inertia::render('Academic/Warnings/Settings', [
            'settings' => $this->settingsPayload(AcademicWarningSetting::forCampus($this->currentCampusId())),
        ]);
    }

    public function updateSettings(UpdateWarningSettingsRequest $request, UpdateWarningSettingsAction $action): RedirectResponse
    {
        $action->run(
            AcademicWarningSetting::forCampus($this->currentCampusId()),
            $request->validated(),
            $request->user(),
        );

        Inertia::flash('success', 'Warning settings saved.');

        return redirect()->route('academic.warnings.settings');
    }

    public function sendAcademicStanding(
        SendAcademicStandingWarningRequest $request,
        GpaCalculation $gpaCalculation,
        SendAcademicStandingWarningAction $action,
    ): RedirectResponse {
        $campusId = $this->currentCampusId();
        $gpaCalculation->loadMissing('student');
        abort_if($campusId && (int) $gpaCalculation->student?->campus_id !== $campusId, 403);

        $result = $action->run(
            $gpaCalculation,
            $request->user(),
            AcademicWarningSetting::forCampus($campusId),
        );

        Inertia::flash($result['duplicate'] ? 'warning' : 'success', $result['message']);

        return back();
    }

    public function sendAttendance(
        SendAttendanceWarningRequest $request,
        CourseOffering $courseOffering,
        Student $student,
        SendAttendanceWarningAction $action,
    ): RedirectResponse {
        $campusId = $this->currentCampusId();
        abort_if($campusId && ((int) $courseOffering->campus_id !== $campusId || (int) $student->campus_id !== $campusId), 403);

        $result = $action->run(
            $courseOffering,
            $student,
            $request->user(),
            AcademicWarningSetting::forCampus($campusId),
        );

        Inertia::flash($result['duplicate'] ? 'warning' : 'success', $result['message']);

        return back();
    }

    private function currentCampusId(): ?int
    {
        $campusId = session('current_campus_id');

        return $campusId ? (int) $campusId : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(AcademicWarningSetting $settings): array
    {
        return [
            'id' => (int) $settings->id,
            'attendance_warning_ratio' => (float) $settings->attendance_warning_ratio,
            'channels' => $settings->activeChannels(),
            'academic_warning_title' => $settings->academic_warning_title,
            'academic_warning_body' => $settings->academic_warning_body,
            'attendance_warning_title' => $settings->attendance_warning_title,
            'attendance_warning_body' => $settings->attendance_warning_body,
            'attendance_exceeded_title' => $settings->attendance_exceeded_title,
            'attendance_exceeded_body' => $settings->attendance_exceeded_body,
        ];
    }
}
