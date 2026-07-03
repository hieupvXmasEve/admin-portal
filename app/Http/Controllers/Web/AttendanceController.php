<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ClassSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cross-offering attendance reporting (ADR 0013 phase B). Per-offering
 * recording lives in the Course Offering Cockpit
 * (RecordClassSessionAttendanceController); this controller no longer
 * exposes create/edit/delete entry points.
 */
class AttendanceController extends Controller
{
    /**
     * Display a listing of attendance records
     */
    public function index(Request $request): Response
    {
        $query = Attendance::with([
            'classSession.courseOffering.unit',
            'classSession.lecture',
            'student',
            'recordedBy',
            'verifiedBy',
        ])->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                    ->orWhereHas('student', function ($q) use ($search) {
                        $q->where('student_id', 'like', "%{$search}%");
                    })
                    ->orWhereHas('classSession', function ($q) use ($search) {
                        $q->where('session_title', 'like', "%{$search}%");
                    })
                    ->orWhereHas('classSession.courseOffering.unit', function ($q) use ($search) {
                        $q->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('session_id')) {
            $query->where('class_session_id', $request->session_id);
        }

        if ($request->filled('recording_method')) {
            $query->where('recording_method', $request->recording_method);
        }

        if ($request->filled('date_from')) {
            $query->whereHas('classSession', function ($q) use ($request) {
                $q->whereDate('session_date', '>=', $request->date_from);
            });
        }

        if ($request->filled('date_to')) {
            $query->whereHas('classSession', function ($q) use ($request) {
                $q->whereDate('session_date', '<=', $request->date_to);
            });
        }

        if ($request->filled('participation_score_min')) {
            $query->where('participation_score', '>=', $request->participation_score_min);
        }

        if ($request->filled('participation_score_max')) {
            $query->where('participation_score', '<=', $request->participation_score_max);
        }

        $attendances = $query->paginate($request->get('per_page', 15));

        // Get statistics
        $totalRecords = Attendance::count();
        $presentCount = Attendance::present()->count();
        $lateCount = Attendance::late()->count();
        $absentCount = Attendance::absent()->count();
        $excusedCount = Attendance::excused()->count();

        // Get filter options
        $statusOptions = [
            'present' => 'Present',
            'late' => 'Late',
            'absent' => 'Absent',
            'excused' => 'Excused',
        ];

        $recordingMethodOptions = [
            'manual' => 'Manual',
            'qr_code' => 'QR Code',
            'rfid' => 'RFID',
            'geolocation' => 'Geolocation',
            'biometric' => 'Biometric',
            'mobile_app' => 'Mobile App',
        ];

        $classSessions = ClassSession::with('courseOffering.unit')
            ->orderBy('session_date', 'desc')
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'title' => $session->session_title,
                    'course' => $session->courseOffering?->unit->code,
                    'date' => $session->session_date->format('M d, Y'),
                ];
            });

        return Inertia::render('attendance/Index', [
            'attendances' => $attendances,
            'filters' => $request->only([
                'search',
                'status',
                'session_id',
                'recording_method',
                'date_from',
                'date_to',
                'participation_score_min',
                'participation_score_max',
                'per_page',
            ]),
            'statusOptions' => $statusOptions,
            'recordingMethodOptions' => $recordingMethodOptions,
            'classSessions' => $classSessions,
            'statistics' => [
                'total_records' => $totalRecords,
                'present' => $presentCount,
                'late' => $lateCount,
                'absent' => $absentCount,
                'excused' => $excusedCount,
            ],
        ]);
    }
}
