<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class AttendanceReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'active_semester_id' => $this->resource['active_semester_id'],
            'semesters' => $this->formatSemesters($this->resource['semesters']),
            'report' => $this->formatReport($this->resource['report']),
        ];
    }

    /**
     * Format semesters list
     */
    protected function formatSemesters(array $semesters): array
    {
        return collect($semesters)->map(function ($semester) {
            return [
                'id' => $semester['id'],
                'name' => $semester['name'],
                'is_active' => $semester['is_active'],
                'start_date' => $semester['start_date'],
                'end_date' => $semester['end_date'],
            ];
        })->toArray();
    }

    /**
     * Format the attendance report
     */
    protected function formatReport(array $report): array
    {
        return [
            'summary' => $this->formatSummary($report['summary']),
            'subjects' => $this->formatSubjects($report['subjects']),
        ];
    }

    /**
     * Format attendance summary
     */
    protected function formatSummary(array $summary): array
    {
        return [
            'total_classes' => $summary['total_classes'],
            'attended' => $summary['attended'],
            'absent' => $summary['absent'],
            'attendance_rate' => $summary['attendance_rate'],
        ];
    }

    /**
     * Format subjects with sessions
     */
    protected function formatSubjects(array $subjects): array
    {
        return collect($subjects)->map(function ($subject) {
            return [
                'unit_code' => $subject['unit_code'],
                'unit_name' => $subject['unit_name'],
                'section_code' => $subject['section_code'],
                'total_sessions' => $subject['total_sessions'],
                'attended' => $subject['attended'],
                'absent' => $subject['absent'],
                'attendance_rate' => $subject['attendance_rate'],
                'sessions' => $this->formatSessions($subject['sessions']),
            ];
        })->toArray();
    }

    /**
     * Format individual sessions
     */
    protected function formatSessions(array $sessions): array
    {
        return collect($sessions)->map(function ($session) {
            return [
                'id' => $session['id'],
                'date' => $session['date'],
                'session_type' => $session['session_type'],
                'status' => $session['status'],
                'status_label' => $session['status_label'],
                'created_at' => $session['created_at'],
                'recorded_by_lecturer' => $session['recorded_by_lecturer'],
                'notes' => $session['notes'],
            ];
        })->toArray();
    }
}
