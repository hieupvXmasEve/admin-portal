<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Models\Attendance;
use App\Models\ClassSession;

final class ListAttendanceRecordsQuery
{
    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function handle(array $filters): array
    {
        $query = Attendance::query()
            ->with(['classSession.courseOffering.unit', 'classSession.lecture', 'student', 'recordedBy', 'verifiedBy'])
            ->orderByDesc('created_at');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(static function ($attendances) use ($search): void {
                $attendances->whereHas('student', static function ($students) use ($search): void {
                    $students->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%");
                })->orWhereHas('classSession', static fn ($sessions) => $sessions->where('session_title', 'like', "%{$search}%"))
                    ->orWhereHas('classSession.courseOffering.unit', static function ($units) use ($search): void {
                        $units->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        foreach (['status', 'session_id' => 'class_session_id', 'recording_method'] as $filter => $column) {
            if (is_int($filter)) {
                $filter = $column;
            }
            if (isset($filters[$filter]) && $filters[$filter] !== '') {
                $query->where($column, $filters[$filter]);
            }
        }
        if (! empty($filters['date_from'])) {
            $query->whereHas('classSession', static fn ($sessions) => $sessions->whereDate('session_date', '>=', $filters['date_from']));
        }
        if (! empty($filters['date_to'])) {
            $query->whereHas('classSession', static fn ($sessions) => $sessions->whereDate('session_date', '<=', $filters['date_to']));
        }
        if (isset($filters['participation_score_min'])) {
            $query->where('participation_score', '>=', $filters['participation_score_min']);
        }
        if (isset($filters['participation_score_max'])) {
            $query->where('participation_score', '<=', $filters['participation_score_max']);
        }

        return [
            'attendances' => $query->paginate((int) ($filters['per_page'] ?? 15)),
            'filters' => $filters,
            'statusOptions' => ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'excused' => 'Excused'],
            'recordingMethodOptions' => ['manual' => 'Manual', 'qr_code' => 'QR Code', 'rfid' => 'RFID', 'geolocation' => 'Geolocation', 'biometric' => 'Biometric', 'mobile_app' => 'Mobile App'],
            'classSessions' => ClassSession::query()->with('courseOffering.unit')->orderByDesc('session_date')->get()->map(static fn ($session): array => [
                'id' => $session->id,
                'title' => $session->session_title,
                'course' => $session->courseOffering?->unit->code,
                'date' => $session->session_date->format('M d, Y'),
            ]),
            'statistics' => [
                'total_records' => Attendance::query()->count(),
                'present' => Attendance::present()->count(),
                'late' => Attendance::late()->count(),
                'absent' => Attendance::absent()->count(),
                'excused' => Attendance::excused()->count(),
            ],
        ];
    }
}
