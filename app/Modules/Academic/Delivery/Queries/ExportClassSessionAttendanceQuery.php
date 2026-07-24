<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\ClassSession;
use App\Modules\Academic\Delivery\Support\ClassSessionService;

final class ExportClassSessionAttendanceQuery
{
    /** @param array<string, mixed> $filters @return array{content: string, filename: string} */
    public function handle(ClassSession $classSession, array $filters): array
    {
        $session = app(ClassSessionService::class)->getSessionWithRelations($classSession->id);
        $attendanceQuery = $session->attendances()->with('student')->orderByDesc('created_at');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $attendanceQuery->whereHas('student', static function ($students) use ($search): void {
                $students->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%");
            });
        }
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $attendanceQuery->where('status', $filters['status']);
        }

        $content = "Student Name,Student ID,Email,Attendance Status,Check In Time,Check Out Time,Minutes Late,Recording Method,Notes,Created At\n";
        foreach ($attendanceQuery->get() as $attendance) {
            $student = $attendance->student;
            $content .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $this->escape($student?->name ?? 'N/A'),
                $this->escape($student?->student_id ?? 'N/A'),
                $this->escape($student?->email ?? 'N/A'),
                $this->escape(ucfirst($attendance->status)),
                $this->escape($attendance->formatted_check_in_time ?? ''),
                $this->escape($attendance->formatted_check_out_time ?? ''),
                $this->escape((string) ($attendance->minutes_late ?? '0')),
                $this->escape(ucfirst($attendance->recording_method ?? 'manual')),
                $this->escape($attendance->notes ?? ''),
                $this->escape($attendance->created_at->format('Y-m-d H:i:s')),
            );
        }

        return [
            'content' => $content,
            'filename' => sprintf('attendance_%s_%s_%s.csv', str_replace(' ', '_', $session->session_title ?? 'session'), $session->session_date->format('Y-m-d'), $session->id),
        ];
    }

    private function escape(string $field): string
    {
        if (str_contains($field, ',') || str_contains($field, '"') || str_contains($field, "\n")) {
            return '"'.str_replace('"', '""', $field).'"';
        }

        return $field;
    }
}
