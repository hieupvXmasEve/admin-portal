<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateClassSessionsRequest;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\Lecture;
use App\Models\Room;
use App\Modules\Academic\Delivery\Support\ClassSessionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClassSessionController extends Controller
{
    public function __construct(protected ClassSessionService $classSessionService) {}

    /**
     * Display a listing of class sessions
     */
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'session_type', 'delivery_mode', 'date_from', 'date_to']);
        $perPage = (int) ($request->input('per_page', 15));
        $perPage = max(1, min($perPage, 100));

        $sessions = $this->classSessionService->getPaginatedSessions($filters, $perPage);

        // Add attendance stats to each session
        $sessions->getCollection()->transform(function ($session) {
            $session->attendance_stats = $session->attendanceStats;

            return $session;
        });

        // Get filter options
        $statusOptions = [
            'scheduled' => 'Scheduled',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];

        $sessionTypeOptions = [
            'lecture' => 'Lecture',
            'tutorial' => 'Tutorial',
            'practical' => 'Practical',
            'workshop' => 'Workshop',
            'seminar' => 'Seminar',
            'exam' => 'Exam',
        ];

        $deliveryModeOptions = [
            'in_person' => 'In Person',
            'online' => 'Online',
            'hybrid' => 'Hybrid',
        ];

        return Inertia::render('class-sessions/Index', [
            'sessions' => $sessions,
            'filters' => array_merge($filters, ['per_page' => $perPage]),
            'statusOptions' => $statusOptions,
            'sessionTypeOptions' => $sessionTypeOptions,
            'deliveryModeOptions' => $deliveryModeOptions,
        ]);
    }

    /**
     * Show the form for creating a new class session
     */
    public function create(): Response
    {
        $courseOfferings = $this->classSessionService->getCourseOfferingsForSelect();

        return Inertia::render('class-sessions/Create', [
            'courseOfferings' => $courseOfferings,
        ]);
    }

    /**
     * Store a newly created class session
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_offering_id' => 'required|exists:course_offerings,id',
            'session_title' => 'required|string|max:255',
            'session_description' => 'nullable|string',
            'session_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'session_type' => 'required|in:lecture,tutorial,practical,workshop,seminar,exam',
            'delivery_mode' => 'required|in:in_person,online,hybrid',
            'status' => 'required|in:scheduled,in_progress,completed,cancelled',
            'attendance_required' => 'boolean',
            'attendance_tracking_enabled' => 'boolean',
            'learning_objectives' => 'nullable|array',
            'required_materials' => 'nullable|array',
            'topics_covered' => 'nullable|array',
            'online_meeting_url' => 'nullable|url',
            'instructor_notes' => 'nullable|string',
            'lecture_id' => 'nullable|exists:lectures,id',
            'room_id' => ['nullable', Rule::exists('rooms', 'id')->where('campus_id', app('campus')->id)],
        ]);

        $classSession = $this->classSessionService->createClassSession($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Class session created successfully',
                'data' => $classSession,
            ]);
        }

        Inertia::flash('message', 'Class session created successfully.');

        return redirect()->back();
    }

    /**
     * Display the specified class session
     */
    public function show(Request $request, ClassSession $classSession)
    {
        $filters = $request->only(['search', 'status']);

        // Get session with relationships
        $session = $this->classSessionService->getSessionWithRelations($classSession->id);
        // Load attendance stats via accessor
        $session->loadMissing('attendances');
        $attendanceStats = $session->attendanceStats;

        // Get all enrolled students for this course offering with their attendance
        $enrolledStudentsQuery = $session->courseOffering
            ->courseRegistrations()
            ->with(['student']);
        // ->where('registration_status', 'confirmed');

        // Get attendance data with filtering (no pagination)
        $attendanceQuery = $session->attendances()
            ->with(['student'])
            ->orderBy('created_at', 'desc');

        // Apply search filter
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $attendanceQuery->whereHas('student', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $attendanceQuery->where('status', $filters['status']);
        }

        $attendanceData = $attendanceQuery->get();

        // Get all enrolled students who don't have attendance records yet
        $studentsWithAttendance = $session->attendances()->pluck('student_id')->toArray();
        $studentsWithoutAttendance = $enrolledStudentsQuery
            ->whereNotIn('student_id', $studentsWithAttendance)
            ->get()
            ->pluck('student');

        // Attendance status options for filtering
        $statusOptions = [
            'all' => 'All Statuses',
            'present' => 'Present',
            'absent' => 'Absent',
            'late' => 'Late',
            'excused' => 'Excused',
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'session' => array_merge($session->toArray(), ['attendance_stats' => $attendanceStats]),
                'attendanceData' => $attendanceData,
                'studentsWithoutAttendance' => $studentsWithoutAttendance,
                'statusOptions' => $statusOptions,
                'filters' => $filters,
            ]);
        }

        return Inertia::render('class-sessions/Show', [
            'session' => array_merge($session->toArray(), ['attendance_stats' => $attendanceStats]),
            'attendanceData' => $attendanceData,
            'studentsWithoutAttendance' => $studentsWithoutAttendance,
            'statusOptions' => $statusOptions,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for editing the specified class session
     */
    public function edit(ClassSession $classSession): Response
    {
        $courseOfferings = $this->classSessionService->getCourseOfferingsForSelect();
        $rooms = Room::forCampus(app('campus')->id)
            ->with('building:id,name,code')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'capacity', 'type', 'building_id']);

        return Inertia::render('class-sessions/Edit', [
            'session' => $classSession,
            'courseOfferings' => $courseOfferings,
            'rooms' => $rooms,
        ]);
    }

    /**
     * Update the specified class session
     */
    public function update(Request $request, ClassSession $classSession)
    {
        $validated = $request->validate([
            'course_offering_id' => 'required|exists:course_offerings,id',
            'session_title' => 'required|string|max:255',
            'session_description' => 'nullable|string',
            'session_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'session_type' => 'required|in:lecture,tutorial,practical,workshop,seminar,exam',
            'delivery_mode' => 'required|in:in_person,online,hybrid',
            'status' => 'required|in:scheduled,in_progress,completed,cancelled',
            'attendance_required' => 'boolean',
            'attendance_tracking_enabled' => 'boolean',
            'learning_objectives' => 'nullable|array',
            'required_materials' => 'nullable|array',
            'topics_covered' => 'nullable|array',
            'online_meeting_url' => 'nullable|url',
            'instructor_notes' => 'nullable|string',
            'lecture_id' => 'nullable|exists:lectures,id',
            'room_id' => [
                'nullable',
                Rule::exists('rooms', 'id')->where('campus_id', app('campus')->id),
            ],
        ]);

        $classSession = $this->classSessionService->updateClassSession($classSession, $validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Class session updated successfully',
                'data' => $classSession,
            ]);
        }

        Inertia::flash('message', 'Class session updated successfully.');

        return redirect()->back();
    }

    /**
     * Generate attendance records for all enrolled students in the class session
     */
    public function generateAttendance(Request $request, ClassSession $classSession)
    {
        $result = $this->classSessionService->generateAttendanceForSession($classSession);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        if ($result['success']) {
            return redirect()->back()
                ->with('success', $result['message']);
        } else {
            return redirect()->back()
                ->with('error', $result['message']);
        }
    }

    /**
     * Export attendance data to CSV
     */
    public function exportAttendance(Request $request, ClassSession $classSession): HttpResponse
    {
        $filters = $request->only(['search', 'status']);

        // Get session with relationships
        $session = $this->classSessionService->getSessionWithRelations($classSession->id);

        // Get attendance data with filtering (no pagination for export)
        $attendanceQuery = $session->attendances()
            ->with(['student'])
            ->orderBy('created_at', 'desc');

        // Apply search filter
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $attendanceQuery->whereHas('student', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $attendanceQuery->where('status', $filters['status']);
        }

        $attendanceData = $attendanceQuery->get();

        // Prepare CSV content
        $csvContent = "Student Name,Student ID,Email,Attendance Status,Check In Time,Check Out Time,Minutes Late,Recording Method,Notes,Created At\n";

        foreach ($attendanceData as $attendance) {
            $student = $attendance->student;
            $csvContent .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $this->escapeCsvField($student?->name ?? 'N/A'),
                $this->escapeCsvField($student?->student_id ?? 'N/A'),
                $this->escapeCsvField($student?->email ?? 'N/A'),
                $this->escapeCsvField(ucfirst($attendance->status)),
                $this->escapeCsvField($attendance->formatted_check_in_time ?? ''),
                $this->escapeCsvField($attendance->formatted_check_out_time ?? ''),
                $this->escapeCsvField($attendance->minutes_late ?? '0'),
                $this->escapeCsvField(ucfirst($attendance->recording_method ?? 'manual')),
                $this->escapeCsvField($attendance->notes ?? ''),
                $this->escapeCsvField($attendance->created_at->format('Y-m-d H:i:s'))
            );
        }

        // Generate filename with session date and ID
        $filename = sprintf(
            'attendance_%s_%s_%s.csv',
            str_replace(' ', '_', $session->session_title ?? 'session'),
            $session->session_date->format('Y-m-d'),
            $session->id
        );

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Escape CSV field to prevent injection and handle commas/quotes
     */
    private function escapeCsvField(?string $field): string
    {
        if ($field === null) {
            return '';
        }

        // Escape quotes by doubling them and wrap in quotes if contains comma, quote, or newline
        if (str_contains($field, ',') || str_contains($field, '"') || str_contains($field, "\n")) {
            return '"'.str_replace('"', '""', $field).'"';
        }

        return $field;
    }

    /**
     * Remove the specified class session
     */
    public function destroy(Request $request, ClassSession $classSession)
    {
        $this->classSessionService->deleteClassSession($classSession);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Class session deleted successfully',
            ]);
        }

        Inertia::flash('message', 'Class session deleted successfully.');

        return redirect()->back();
    }

    /**
     * Show the Add Session modal for a specific course offering.
     * Props are minimal — rooms + lecturers for the campus.
     */
    public function createForOffering(CourseOffering $courseOffering): Response
    {
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        $rooms = Room::bookable()
            ->withStatus(Room::STATUS_AVAILABLE)
            ->forCampus($courseOffering->campus_id)
            ->with('building:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'capacity', 'type', 'building_id']);

        $lecturers = Lecture::active()
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        return Inertia::render('class-sessions/modals/Add', [
            'courseOffering' => [
                'id' => $courseOffering->id,
                'course_code' => $courseOffering->course_code,
                'course_title' => $courseOffering->course_title,
                'campus_id' => $courseOffering->campus_id,
                'schedule_time_start' => $courseOffering->schedule_time_start?->format('H:i'),
                'schedule_time_end' => $courseOffering->schedule_time_end?->format('H:i'),
                'syllabus_template' => $courseOffering->syllabusTemplate
                    ? ['total_sessions' => $courseOffering->syllabusTemplate->total_sessions]
                    : null,
                'class_sessions_count' => $courseOffering->classSessions()->count(),
            ],
            'rooms' => $rooms,
            'lecturers' => $lecturers,
        ]);
    }

    /**
     * Show the QuickEdit Session modal.
     */
    public function editModal(ClassSession $classSession): Response
    {
        $courseOffering = $classSession->courseOffering;
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        $rooms = Room::bookable()
            ->withStatus(Room::STATUS_AVAILABLE)
            ->forCampus($courseOffering->campus_id)
            ->with('building:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'capacity', 'type', 'building_id']);

        $lecturers = Lecture::active()
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        $classSession->load('room:id,name', 'lecture:id,first_name,last_name');

        return Inertia::render('class-sessions/modals/QuickEdit', [
            'session' => $classSession,
            'rooms' => $rooms,
            'lecturers' => $lecturers,
        ]);
    }

    /**
     * Show the BulkEdit modal for selected sessions.
     * Sessions are passed as query param IDs — loaded server-side.
     */
    public function bulkEditModal(Request $request, CourseOffering $courseOffering): Response
    {
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        $sessionIds = array_filter(explode(',', $request->query('ids', '')));

        $sessions = ClassSession::whereIn('id', $sessionIds)
            ->where('course_offering_id', $courseOffering->id)
            ->get(['id', 'session_title', 'session_date', 'start_time', 'end_time', 'course_offering_id']);

        $rooms = Room::bookable()
            ->withStatus(Room::STATUS_AVAILABLE)
            ->forCampus($courseOffering->campus_id)
            ->with('building:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'capacity', 'type', 'building_id']);

        $lecturers = Lecture::active()
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);

        return Inertia::render('class-sessions/modals/BulkEdit', [
            'courseOffering' => ['id' => $courseOffering->id],
            'sessions' => $sessions,
            'rooms' => $rooms,
            'lecturers' => $lecturers,
        ]);
    }

    /**
     * Bulk delete class sessions (web route for Inertia router.delete)
     */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:class_sessions,id',
        ]);

        $count = 0;
        foreach ($validated['ids'] as $id) {
            $session = ClassSession::find($id);
            if ($session) {
                $this->classSessionService->deleteClassSession($session);
                $count++;
            }
        }

        Inertia::flash('message', "{$count} session(s) deleted successfully.");

        return redirect()->back();
    }

    /**
     * Generate class sessions for a course offering (web route for Inertia useForm)
     */
    public function generate(GenerateClassSessionsRequest $request, CourseOffering $courseOffering)
    {
        $validated = $request->validated();

        $sessions = $this->classSessionService->generateClassSessions(
            $courseOffering,
            $validated['room_id'],
            isset($validated['start_date']) ? Carbon::parse($validated['start_date']) : null,
            $validated['weekly_schedule'],
            $validated['excluded_dates'] ?? []
        );

        Inertia::flash('message', "{$sessions->count()} class sessions generated successfully.");

        return redirect()->back();
    }
}
