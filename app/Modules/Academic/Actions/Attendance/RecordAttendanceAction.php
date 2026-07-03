<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions\Attendance;

use App\Models\Attendance;

/**
 * Single source of truth for recording one attendance record. Used by the
 * Course Offering Cockpit's per-session recording endpoint (ADR 0013 phase B);
 * the standalone staff Attendance pages no longer record attendance and are
 * reporting-only.
 */
class RecordAttendanceAction
{
    public const STATUSES = ['present', 'late', 'absent', 'excused'];

    public const RECORDING_METHODS = ['manual', 'qr_code', 'rfid', 'geolocation', 'biometric', 'mobile_app'];

    /**
     * Records one student's attendance for one session. Keyed on the
     * (class_session_id, student_id) unique constraint so re-recording a
     * session already covered by auto-system attendance confirms it manually
     * instead of failing on a duplicate-key error.
     *
     * `recorded_by_lecture_id` is FK-constrained to the `lectures` table, so
     * it only ever holds a Lecture id (set by the lecturer API's own
     * recording path). Staff recording attendance from the web app act as a
     * `User`, not a `Lecture` — passing a User id here would violate the FK,
     * so staff-recorded rows leave it null.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function run(array $attributes, ?int $recordedByLectureId = null): Attendance
    {
        return Attendance::updateOrCreate(
            [
                'class_session_id' => $attributes['class_session_id'],
                'student_id' => $attributes['student_id'],
            ],
            [...$attributes, 'recorded_by_lecture_id' => $recordedByLectureId]
        );
    }
}
