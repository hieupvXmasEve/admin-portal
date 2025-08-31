<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * Display a listing of attendance records
     */
    public function index(Request $request): Response
    {
        $query = Attendance::with([
            'classSession.courseOffering.curriculumUnit.unit',
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
                    ->orWhereHas('classSession.courseOffering.curriculumUnit.unit', function ($q) use ($search) {
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

    /**
     * Show the form for creating a new attendance record
     */
    public function create(Request $request): Response
    {
        $sessionId = $request->get('session_id');

        $classSessions = ClassSession::with('courseOffering.curriculumUnit.unit')
            ->orderBy('session_date', 'desc')
            ->get();

        $students = Student::get();

        return Inertia::render('attendance/Create', [
            'classSessions' => $classSessions,
            'students' => $students,
            'preselectedSessionId' => $sessionId,
        ]);
    }

    /**
     * Store a newly created attendance record
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_session_id' => 'required|exists:class_sessions,id',
            'student_id' => 'required|exists:students,id',
            'status' => 'required|in:present,late,absent,excused',
            'check_in_time' => 'nullable|date',
            'check_out_time' => 'nullable|date|after:check_in_time',
            'minutes_late' => 'nullable|integer|min:0',
            'minutes_present' => 'nullable|integer|min:0',
            'recording_method' => 'required|in:manual,qr_code,rfid,geolocation,biometric,mobile_app',
            'participation_level' => 'nullable|in:excellent,good,average,poor',
            'participation_score' => 'nullable|numeric|min:0|max:100',
            'participation_notes' => 'nullable|string',
            'notes' => 'nullable|string',
            'excuse_reason' => 'nullable|string',
        ]);

        $validated['recorded_by_lecture_id'] = auth()->id();

        $attendance = Attendance::create($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Attendance record created successfully',
                'data' => $attendance->load(['classSession', 'student']),
            ]);
        }

        return redirect()->route('attendance.index')
            ->with('success', 'Attendance record created successfully');
    }

    /**
     * Display the specified attendance record
     */
    public function show(Attendance $attendance): Response
    {
        $attendance->load([
            'classSession.courseOffering.curriculumUnit.unit',
            'classSession.lecture',
            'student',
            'recordedBy',
            'verifiedBy',
        ]);

        return Inertia::render('attendance/Show', [
            'attendance' => $attendance,
        ]);
    }

    /**
     * Show the form for editing the specified attendance record
     */
    public function edit(Attendance $attendance): Response
    {
        $attendance->load(['classSession.courseOffering.curriculumUnit.unit', 'student']);

        $classSessions = ClassSession::with('courseOffering.curriculumUnit.unit')
            ->orderBy('session_date', 'desc')
            ->get();

        $students = Student::get();

        return Inertia::render('attendance/Edit', [
            'attendance' => $attendance,
            'classSessions' => $classSessions,
            'students' => $students,
        ]);
    }

    /**
     * Update the specified attendance record
     */
    public function update(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'class_session_id' => 'required|exists:class_sessions,id',
            'student_id' => 'required|exists:students,id',
            'status' => 'required|in:present,late,absent,excused',
            'check_in_time' => 'nullable|date',
            'check_out_time' => 'nullable|date|after:check_in_time',
            'minutes_late' => 'nullable|integer|min:0',
            'minutes_present' => 'nullable|integer|min:0',
            'recording_method' => 'required|in:manual,qr_code,rfid,geolocation,biometric,mobile_app',
            'participation_level' => 'nullable|in:excellent,good,average,poor',
            'participation_score' => 'nullable|numeric|min:0|max:100',
            'participation_notes' => 'nullable|string',
            'notes' => 'nullable|string',
            'excuse_reason' => 'nullable|string',
            'is_verified' => 'boolean',
        ]);

        if ($validated['is_verified'] ?? false) {
            $validated['verified_by_user_id'] = auth()->id();
            $validated['verified_at'] = now();
        }

        $attendance->update($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Attendance record updated successfully',
                'data' => $attendance->load(['classSession', 'student']),
            ]);
        }

        return redirect()->route('attendance.index')
            ->with('success', 'Attendance record updated successfully');
    }

    /**
     * Remove the specified attendance record
     */
    public function destroy(Request $request, Attendance $attendance)
    {
        $attendance->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Attendance record deleted successfully',
            ]);
        }

        return redirect()->route('attendance.index')
            ->with('success', 'Attendance record deleted successfully');
    }

    /**
     * Bulk update attendance records
     */
    public function bulkUpdate(Request $request)
    {
        $validated = $request->validate([
            'attendance_ids' => 'required|array',
            'attendance_ids.*' => 'exists:attendances,id',
            'status' => 'required|in:present,late,absent,excused',
            'recording_method' => 'nullable|in:manual,qr_code,rfid,geolocation,biometric,mobile_app',
            'is_verified' => 'boolean',
        ]);

        $updateData = ['status' => $validated['status']];

        if (isset($validated['recording_method'])) {
            $updateData['recording_method'] = $validated['recording_method'];
        }

        if ($validated['is_verified'] ?? false) {
            $updateData['is_verified'] = true;
            $updateData['verified_by_user_id'] = auth()->id();
            $updateData['verified_at'] = now();
        }

        $updated = Attendance::whereIn('id', $validated['attendance_ids'])
            ->update($updateData);

        return response()->json([
            'success' => true,
            'message' => "Successfully updated {$updated} attendance records",
        ]);
    }
}
