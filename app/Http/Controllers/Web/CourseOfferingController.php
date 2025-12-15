<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Constants\CourseOfferingRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseOfferingRequest;
use App\Http\Requests\UpdateCourseOfferingRequest;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Lecture;
use App\Models\Room;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\CampusLogContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

use App\Services\SystemConfigService;
use App\Models\Form;
use App\Models\FormSurvey;
use App\Models\FormVersion;

use App\Services\CourseSurveyService;

class CourseOfferingController extends Controller
{
    public function __construct(
        protected SystemConfigService $systemConfigService,
        protected CourseSurveyService $courseSurveyService
    ) {
    }

    /**
     * Display a listing of course offerings
     */
    public function index(Request $request): Response
    {
        $query = CourseOffering::with(['semester', 'lecture', 'unit', 'formSurveys'])
            ->join('units', 'course_offerings.unit_id', '=', 'units.id')
            ->where('course_offerings.campus_id', app('campus')->id)
            ->whereNotNull('units.id')
            ->select('course_offerings.*')
            ->orderBy('units.code');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('course_offerings.section_code', 'like', "%{$search}%")
                    ->orWhere('course_offerings.location', 'like', "%{$search}%")
                    ->orWhere('units.code', 'like', "%{$search}%")
                    ->orWhere('units.name', 'like', "%{$search}%");
            });
        }

        // Default to current semester if no semester filter is provided
        $defaultSemesterId = null;
        if (! $request->filled('semester_id') || $request->semester_id === 'all') {
            $currentSemester = Semester::getActiveSemester();
            if ($currentSemester) {
                $defaultSemesterId = $currentSemester->id;
                $query->where('course_offerings.semester_id', $currentSemester->id);
            }
        } elseif ($request->semester_id !== 'all') {
            $query->where('course_offerings.semester_id', $request->semester_id);
        }

        if ($request->filled('enrollment_status') && $request->enrollment_status !== 'all') {
            $query->where('course_offerings.enrollment_status', $request->enrollment_status);
        }

        if ($request->filled('course_status') && $request->course_status !== 'all') {
            $query->where('course_offerings.course_status', $request->course_status);
        }

        if ($request->filled('delivery_mode') && $request->delivery_mode !== 'all') {
            $query->where('course_offerings.delivery_mode', $request->delivery_mode);
        }

        // Add unit level filter
        if ($request->filled('unit_level') && $request->unit_level !== 'all') {
            $query->where('units.level', $request->unit_level);
        }

        // Add unit type filter
        if ($request->filled('unit_type') && $request->unit_type !== 'all') {
            $query->where('units.unit_type', $request->unit_type);
        }

        $courseOfferings = $query->paginate(15)->withQueryString();

        // Get filter options
        $semesters = Semester::orderBy('start_date', 'desc')->get(['id', 'name', 'code']);

        // Get unique unit levels for filter options
        $unitLevels = Unit::select('level')
            ->whereNotNull('level')
            ->distinct()
            ->orderBy('level')
            ->pluck('level')
            ->map(function ($level) {
                return ['value' => $level, 'label' => "Level {$level}"];
            })
            ->toArray();

        // Get unique unit types for filter options
        $unitTypes = Unit::select('unit_type')
            ->whereNotNull('unit_type')
            ->distinct()
            ->orderBy('unit_type')
            ->pluck('unit_type')
            ->map(function ($type) {
                $labels = [
                    'general' => 'General',
                    'egc' => 'English Global Citizen',
                    'semi' => 'Semiconductor',
                    'ai' => 'Artificial Intelligence',
                    'mkt' => 'Marketing',
                    'ba' => 'Business Administration',
                    'cs' => 'Computer Science',
                    'ee' => 'Electrical Engineering',
                    'me' => 'Mechanical Engineering',
                    'fin' => 'Finance',
                ];

                return ['value' => $type, 'label' => $labels[$type] ?? ucfirst($type)];
            })
            ->toArray();

        // Set default filters
        $filters = $request->only(['search', 'semester_id', 'enrollment_status', 'course_status', 'delivery_mode', 'unit_level', 'unit_type']);

        // Only set default semester if no semester filter is provided
        if (! $request->filled('semester_id') && $defaultSemesterId) {
            $filters['semester_id'] = $defaultSemesterId;
        }

        // Check if survey feature is enabled and get default form
        $config = $this->systemConfigService->getConfig();
        $surveyEnabled = $config['survey_enabled'] ?? false;
        $defaultSurveyFormId = $config['default_course_survey'] ?? null;
        $defaultSurveyForm = null;

        if ($surveyEnabled && $defaultSurveyFormId) {
            $defaultSurveyForm = Form::find($defaultSurveyFormId);
        }

        return Inertia::render('course-offerings/Index', [
            'courseOfferings' => $courseOfferings,
            'filters' => $filters,
            'semesters' => $semesters,
            'unitLevels' => $unitLevels,
            'unitTypes' => $unitTypes,
            'surveyConfig' => [
                'enabled' => $surveyEnabled,
                'defaultFormId' => $defaultSurveyFormId,
                'defaultFormTitle' => $defaultSurveyForm?->title,
            ],
            'enrollmentStatusOptions' => [
                ['value' => 'open', 'label' => 'Open'],
                ['value' => 'closed', 'label' => 'Closed'],
                ['value' => 'waitlist_only', 'label' => 'Waitlist Only'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
            'courseStatusOptions' => [
                ['value' => 'not_started', 'label' => 'Not Started'],
                ['value' => 'in_progress', 'label' => 'In Progress'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ],
            'deliveryModeOptions' => [
                ['value' => 'in_person', 'label' => 'In Person'],
                ['value' => 'online', 'label' => 'Online'],
                ['value' => 'hybrid', 'label' => 'Hybrid'],
                ['value' => 'blended', 'label' => 'Blended'],
            ],
        ]);
    }

    /**
     * Create a survey for the course offering
     */
    public function createSurvey(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        $config = $this->systemConfigService->getConfig();
        $surveyEnabled = $config['survey_enabled'] ?? false;
        $defaultSurveyFormId = $config['default_course_survey'] ?? null;

        if (!$surveyEnabled || !$defaultSurveyFormId) {
            return Redirect::back()->with('error', 'Survey feature is not enabled or no default survey form is configured.');
        }

        // Check if survey already exists
        if ($courseOffering->formSurveys()->exists()) {
            return Redirect::back()->with('error', 'A survey has already been created for this course offering.');
        }

        // Attempt to create survey using service
        // This will create the form survey AND assign it to all current students
        $success = $this->courseSurveyService->attachSurveyToCompletedCourse($courseOffering);

        if ($success) {
            return Redirect::back()->with('success', 'Survey created and assigned to students successfully.');
        }

        // Handle specific failure cases based on service logic
        if ($courseOffering->unit && $courseOffering->unit->unit_type === 'egc') {
            return Redirect::back()->with('error', 'Surveys cannot be created for EGC units.');
        }

        return Redirect::back()->with('error', 'Failed to create survey. Please ensure the default survey form is active and has a published version.');
    }

    /**
     * Show the form for creating a new course offering
     */
    public function create(): Response
    {
        // Get the currently active semester
        $activeSemester = Semester::getActiveSemester();

        // If no active semester, return with error state
        if (! $activeSemester) {
            return Inertia::render('course-offerings/Create', [
                'activeSemester' => null,
                'units' => [],
                'lectures' => [],
                'error' => 'No semester is currently active. Please activate a semester before creating course offerings.',
            ]);
        }

        // Get all units from the system, sorted by code
        $units = Unit::orderBy('code')
            ->get(['id', 'code', 'name', 'credit_points', 'level', 'unit_type'])
            ->map(function ($unit) {
                return [
                    'unit_id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'credit_points' => $unit->credit_points,
                    'level' => $unit->level,
                    'unit_type' => $unit->unit_type,
                ];
            });

        // Get available lectures
        $lectures = Lecture::active()
            ->availableForAssignment()
            ->orderByName()
            ->get(['id', 'first_name', 'last_name', 'email', 'academic_rank']);

        // Get available syllabus templates (now loaded dynamically when unit is selected)
        $syllabusTemplates = \App\Models\SyllabusTemplate::where('is_active', true)
            ->with(['unit:id,code,name', 'applicableCampus:id,name', 'applicableProgram:id,name'])
            ->orderBy('title')
            ->get(['id', 'unit_id', 'title', 'version', 'description', 'applicable_campus_id', 'applicable_program_id', 'delivery_mode']);

        return Inertia::render('course-offerings/Create', [
            'activeSemester' => [
                'id' => $activeSemester->id,
                'name' => $activeSemester->name,
                'code' => $activeSemester->code,
                'start_date' => $activeSemester->start_date,
                'end_date' => $activeSemester->end_date,
            ],
            'units' => $units,
            'lectures' => $lectures,
            'syllabusTemplates' => $syllabusTemplates,
            'error' => null,
        ]);
    }

    /**
     * Store a newly created course offering
     */
    public function store(StoreCourseOfferingRequest $request): RedirectResponse
    {
        $validatedData = $request->validated();
        $validatedData['campus_id'] = app('campus')->id;

        CourseOffering::create($validatedData);

        return Redirect::route(CourseOfferingRoutes::INDEX)
            ->with('success', 'Course offering created successfully.');
    }

    /**
     * Display the specified course offering
     */
    public function show(CourseOffering $courseOffering): Response
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        $courseOffering->load([
            'semester',
            'unit',
            'syllabusTemplate.unit:id,code,name',
            'syllabusTemplate.applicableCampus:id,name',
            'syllabusTemplate.applicableProgram:id,name',
            'lecture',
            'courseRegistrations' => function ($query) {
                $query->with('student')
                    ->orderBy('registration_date', 'desc');
            },
            'academicRecords' => function ($query) {
                $query->select(['id', 'student_id', 'course_offering_id', 'is_repeat_course', 'attempt_number', 'original_record_id'])
                    ->with('originalRecord:id,final_letter_grade,final_percentage,completion_status');
            },
            'classSessions' => function ($query) {
                $query->with('room:id,name', 'lecture:id,first_name,last_name')
                    ->select(['id', 'course_offering_id', 'room_id', 'lecture_id', 'session_title', 'session_description', 'session_date', 'start_time', 'end_time', 'session_type', 'status', 'attendance_percentage'])
                    ->orderBy('session_date')
                    ->orderBy('start_time');
            },
        ]);

        // Get available rooms for class session generation
        $conflictingRoomIds = [];

        // Only filter by schedule if the offering has defined days and time range
        $scheduleDays = $courseOffering->schedule_days ?? [];
        $startTime = $courseOffering->schedule_time_start ? $courseOffering->schedule_time_start->format('H:i') : null;
        $endTime = $courseOffering->schedule_time_end ? $courseOffering->schedule_time_end->format('H:i') : null;

        if (! empty($scheduleDays) && $startTime && $endTime) {
            // Map schedule days to MySQL WEEKDAY() indexes (Monday=0 ... Sunday=6)
            $dayToIndex = [
                'monday' => 0,
                'tuesday' => 1,
                'wednesday' => 2,
                'thursday' => 3,
                'friday' => 4,
                'saturday' => 5,
                'sunday' => 6,
            ];
            $weekdayIndexes = collect($scheduleDays)
                ->map(fn($d) => strtolower($d))
                ->map(fn($d) => $dayToIndex[$d] ?? null)
                ->filter(static fn($v) => $v !== null)
                ->values()
                ->all();

            if (! empty($weekdayIndexes)) {
                $roomIdsInCampus = Room::forCampus(app('campus')->id)->pluck('id');

                $conflictingRoomIds = ClassSession::whereIn('room_id', $roomIdsInCampus)
                    ->whereIn(DB::raw('WEEKDAY(session_date)'), $weekdayIndexes)
                    ->where('status', '!=', 'cancelled')
                    ->where(function ($q) use ($startTime, $endTime) {
                        $q->where(function ($subQ) use ($startTime) {
                            // Existing session overlaps new start
                            $subQ->whereTime('start_time', '<=', $startTime)
                                ->whereTime('end_time', '>', $startTime);
                        })->orWhere(function ($subQ) use ($endTime) {
                            // Existing session overlaps new end
                            $subQ->whereTime('start_time', '<', $endTime)
                                ->whereTime('end_time', '>=', $endTime);
                        })->orWhere(function ($subQ) use ($startTime, $endTime) {
                            // Existing session fully within the new window
                            $subQ->whereTime('start_time', '>=', $startTime)
                                ->whereTime('end_time', '<=', $endTime);
                        });
                    })
                    ->pluck('room_id')
                    ->toArray();
            }
        }

        $availableRooms = Room::bookable()
            ->withStatus(Room::STATUS_AVAILABLE)
            ->forCampus(app('campus')->id)
            ->with('building:id,name')
            ->when(! empty($conflictingRoomIds), function ($q) use ($conflictingRoomIds) {
                $q->whereNotIn('id', $conflictingRoomIds);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'capacity', 'type']);

        // $canGenerateClassSessions = $courseOffering->canGenerateClassSessions();
        return Inertia::render('course-offerings/Show', [
            'courseOffering' => $courseOffering,
            'availableRooms' => $availableRooms,
            // 'canGenerateClassSessions' => $canGenerateClassSessions,
        ]);
    }

    /**
     * Show the form for editing the specified course offering
     */
    public function edit(CourseOffering $courseOffering): Response|RedirectResponse
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        // Restrict editing for completed or cancelled courses
        if (! $courseOffering->canModify()) {
            return Redirect::back()
                ->with('error', 'Cannot edit a course that is completed or cancelled.');
        }

        // Load the course offering with its related semester and unit data
        $courseOffering->load([
            'semester:id,name,code,start_date,end_date',
            'unit:id,code,name,credit_points',
            'syllabusTemplate:id,title,version,description',
        ]);

        // Get available lectures
        $lectures = Lecture::active()
            ->availableForAssignment()
            ->orderByName()
            ->get(['id', 'first_name', 'last_name', 'email', 'academic_rank']);

        // Get available syllabus templates of unit
        $syllabusTemplates = \App\Models\SyllabusTemplate::where('is_active', true)
            ->where('unit_id', $courseOffering->unit_id)
            ->with(['unit:id,code,name', 'applicableCampus:id,name', 'applicableProgram:id,name'])
            ->orderBy('title')
            ->get(['id', 'unit_id', 'title', 'version', 'description', 'applicable_campus_id', 'applicable_program_id', 'delivery_mode']);

        return Inertia::render('course-offerings/Edit', [
            'courseOffering' => $courseOffering,
            'lectures' => $lectures,
            'syllabusTemplates' => $syllabusTemplates,
        ]);
    }

    /**
     * Update the specified course offering
     */
    public function update(UpdateCourseOfferingRequest $request, CourseOffering $courseOffering): RedirectResponse
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        // Restrict updating for completed or cancelled courses
        if (! $courseOffering->canModify()) {
            return Redirect::back()
                ->with('error', 'Cannot update a course that is completed or cancelled.');
        }

        try {

            $courseOffering->update($request->validated());

            return Redirect::route(CourseOfferingRoutes::INDEX)
                ->with('success', 'Course offering updated successfully.');
        } catch (\Throwable $th) {
            Log::error('Failed to update course offering: ' . $th->getMessage());

            return Redirect::back()
                ->with('error', 'Failed to update course offering: ' . $th->getMessage());
        }
    }

    /**
     * Remove the specified course offering
     */
    public function destroy(CourseOffering $courseOffering): RedirectResponse
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        // Restrict deleting for completed courses
        if ($courseOffering->isCourseCompleted()) {
            return Redirect::back()
                ->with('error', 'Cannot delete a completed course. You can only view or duplicate it.');
        }

        // Check if course offering has scheduled sessions
        $scheduledSessionsCount = $courseOffering->classSessions()->count();

        // Check if course offering has enrolled students
        $enrolledStudentsCount = $courseOffering->current_enrollment;

        if ($scheduledSessionsCount > 0 || $enrolledStudentsCount > 0) {
            $reasons = [];
            if ($scheduledSessionsCount > 0) {
                $reasons[] = "{$scheduledSessionsCount} scheduled session(s)";
            }
            if ($enrolledStudentsCount > 0) {
                $reasons[] = "{$enrolledStudentsCount} enrolled student(s)";
            }

            $reasonText = implode(' and ', $reasons);

            return Redirect::back()
                ->with('error', "Cannot delete course offering because it has {$reasonText}. Please cancel the course offering instead or remove all sessions and students first.");
        }

        try {
            DB::beginTransaction();

            // Get registration count for notification
            $registrationCount = $courseOffering->courseRegistrations()->count();

            // Delete all associated course registrations first
            $courseOffering->courseRegistrations()->delete();

            // Delete the course offering
            $courseOffering->delete();

            DB::commit();

            $message = 'Course offering deleted successfully.';
            if ($registrationCount > 0) {
                $message .= " {$registrationCount} associated registration(s) were also removed.";
            }

            return Redirect::route(CourseOfferingRoutes::INDEX)
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete course offering: ' . $e->getMessage());

            return Redirect::back()
                ->with('error', 'Failed to delete course offering: ' . $e->getMessage());
        }
    }

    /**
     * Bulk delete course offerings
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:course_offerings,id',
        ]);

        try {
            DB::beginTransaction();

            $courseOfferings = CourseOffering::whereIn('id', $request->ids)
                ->where('campus_id', app('campus')->id)
                ->get();

            // Check for course offerings that cannot be deleted
            $cannotDelete = [];
            foreach ($courseOfferings as $courseOffering) {
                $scheduledSessionsCount = $courseOffering->classSessions()->count();
                $enrolledStudentsCount = $courseOffering->current_enrollment;

                if ($scheduledSessionsCount > 0 || $enrolledStudentsCount > 0) {
                    $reasons = [];
                    if ($scheduledSessionsCount > 0) {
                        $reasons[] = "{$scheduledSessionsCount} scheduled session(s)";
                    }
                    if ($enrolledStudentsCount > 0) {
                        $reasons[] = "{$enrolledStudentsCount} enrolled student(s)";
                    }

                    $unitCode = $courseOffering->unit?->code ?? 'Unknown';
                    $cannotDelete[] = "{$unitCode}: " . implode(' and ', $reasons);
                }
            }

            if (! empty($cannotDelete)) {
                DB::rollBack();
                $reasonText = implode('; ', $cannotDelete);

                return Redirect::back()
                    ->with('error', "Cannot delete the following course offerings: {$reasonText}. Please cancel these course offerings instead or remove all sessions and students first.");
            }

            $totalRegistrations = 0;

            foreach ($courseOfferings as $courseOffering) {
                // Count registrations for notification
                $registrationCount = $courseOffering->courseRegistrations()->count();
                $totalRegistrations += $registrationCount;

                // Delete associated course registrations first
                $courseOffering->courseRegistrations()->delete();
            }

            // Delete the course offerings
            CourseOffering::whereIn('id', $request->ids)
                ->where('campus_id', app('campus')->id)
                ->delete();

            DB::commit();

            $message = 'Selected course offerings deleted successfully.';
            if ($totalRegistrations > 0) {
                $message .= " {$totalRegistrations} associated registration(s) were also removed.";
            }

            return Redirect::route(CourseOfferingRoutes::INDEX)
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk delete course offerings: ' . $e->getMessage());

            return Redirect::back()
                ->with('error', 'Failed to delete course offerings: ' . $e->getMessage());
        }
    }

    /**
     * Toggle course offering status
     */
    public function toggleStatus(CourseOffering $courseOffering): RedirectResponse
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        $newStatus = $courseOffering->enrollment_status === 'open' ? 'closed' : 'open';
        $courseOffering->update(['enrollment_status' => $newStatus]);

        return Redirect::back()
            ->with('success', "Course offering status updated to {$newStatus}.");
    }

    /**
     * Bulk update class sessions for a course offering
     * Only allows updating: start_time, end_time, lecture_id, room_id
     */
    public function bulkUpdateClassSessions(Request $request, CourseOffering $courseOffering)
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        $validated = $request->validate([
            'session_ids' => ['required', 'array', 'min:1'],
            'session_ids.*' => ['required', 'integer', 'exists:class_sessions,id'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => [
                'nullable',
                'date_format:H:i',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value && $request->start_time) {
                        $start = \Carbon\Carbon::createFromFormat('H:i', $request->start_time);
                        $end = \Carbon\Carbon::createFromFormat('H:i', $value);
                        if ($end->lte($start)) {
                            $fail('The end time must be after start time.');
                        }
                    }
                },
            ],
            'lecture_id' => ['nullable', 'integer', 'exists:lectures,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ]);

        // Verify all sessions belong to this course offering
        $sessionIds = $validated['session_ids'];
        $sessions = ClassSession::whereIn('id', $sessionIds)
            ->where('course_offering_id', $courseOffering->id)
            ->get();

        if ($sessions->count() !== count($sessionIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Some sessions do not belong to this course offering.',
            ], 422);
        }

        // // Check if any session is completed or in_progress - cannot update those
        // $completedOrInProgress = $sessions->whereIn('status', ['completed', 'in_progress']);
        // if ($completedOrInProgress->isNotEmpty()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Cannot update completed or in-progress sessions.',
        //     ], 422);
        // }

        try {
            DB::beginTransaction();

            // Build update data - only include provided fields
            // Note: For TIME columns, we need to ensure proper format for mass update
            $updateData = [];
            if (isset($validated['start_time'])) {
                // Format time string properly for MySQL TIME column (H:i:s format)
                $updateData['start_time'] = $validated['start_time'] . ':00';
            }
            if (isset($validated['end_time'])) {
                // Format time string properly for MySQL TIME column (H:i:s format)
                $updateData['end_time'] = $validated['end_time'] . ':00';
            }
            if (isset($validated['lecture_id'])) {
                $updateData['lecture_id'] = $validated['lecture_id'];
            }
            if (isset($validated['room_id'])) {
                $updateData['room_id'] = $validated['room_id'];
            }

            // Calculate duration if times are being updated
            if (isset($updateData['start_time']) && isset($updateData['end_time'])) {
                // Both times provided - calculate duration once
                $start = \Carbon\Carbon::createFromFormat('H:i:s', $updateData['start_time']);
                $end = \Carbon\Carbon::createFromFormat('H:i:s', $updateData['end_time']);
                $updateData['duration_minutes'] = $start->diffInMinutes($end);

                // Update all sessions using mass update (better performance)
                ClassSession::whereIn('id', $sessionIds)->update($updateData);
            } elseif (isset($updateData['start_time']) || isset($updateData['end_time'])) {
                // Only one time provided - need to update individually to recalculate duration
                // This is because each session may have different existing time values
                foreach ($sessions as $session) {
                    $sessionUpdateData = $updateData;
                    $sessionStart = isset($updateData['start_time'])
                        ? $updateData['start_time']
                        : ($session->start_time ? $session->start_time->format('H:i:s') : null);
                    $sessionEnd = isset($updateData['end_time'])
                        ? $updateData['end_time']
                        : ($session->end_time ? $session->end_time->format('H:i:s') : null);

                    if ($sessionStart && $sessionEnd) {
                        $start = \Carbon\Carbon::createFromFormat('H:i:s', $sessionStart);
                        $end = \Carbon\Carbon::createFromFormat('H:i:s', $sessionEnd);
                        $sessionUpdateData['duration_minutes'] = $start->diffInMinutes($end);
                    }

                    // Use mass update per session to avoid triggering individual events
                    ClassSession::where('id', $session->id)->update($sessionUpdateData);
                }
            } else {
                // No time updates - can use mass update for all sessions
                ClassSession::whereIn('id', $sessionIds)->update($updateData);
            }

            DB::commit();

            // Log bulk update activity (single log entry for the entire operation)
            $this->logBulkUpdateActivity($courseOffering, $sessions, $updateData);

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$sessions->count()} class session(s).",
                'data' => [
                    'updated_count' => $sessions->count(),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to bulk update class sessions: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update class sessions: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Log bulk update activity for class sessions
     */
    private function logBulkUpdateActivity(CourseOffering $courseOffering, $sessions, array $updateData): void
    {
        $sessionCount = $sessions->count();
        $sessionIds = $sessions->pluck('id')->toArray();
        $changedFields = array_keys($updateData);

        // Get campus-aware log name
        $logName = CampusLogContext::getLogName('ClassSession', $courseOffering->campus_id);

        // Build properties
        $properties = CampusLogContext::enhanceLogProperties([
            'operation' => 'bulk_update',
            'session_count' => $sessionCount,
            'session_ids' => $sessionIds,
            'changed_fields' => $changedFields,
            'update_data' => $updateData,
            'course_offering_id' => $courseOffering->id,
            'course_code' => $courseOffering->course_code,
        ], $courseOffering->campus_id);

        // Log the activity
        activity($logName)
            ->performedOn($courseOffering)
            ->causedBy(Auth::user())
            ->withProperties($properties)
            ->event('bulk_updated')
            ->log("Bulk updated {$sessionCount} class session(s) for course offering {$courseOffering->course_code}");
    }

    /**
     * Change the room for all existing class sessions of this course offering.
     * Validates that the selected room is available on the configured schedule days
     * and within the time range (schedule_time_start to schedule_time_end).
     */
    public function changeRoom(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        $validated = $request->validate([
            'room_id' => ['required', Rule::exists('rooms', 'id')],
        ]);

        $roomId = (int) $validated['room_id'];

        // Ensure there are class sessions to update
        if (! $courseOffering->classSessions()->exists()) {
            return Redirect::back()->with('error', 'No class sessions exist for this course offering.');
        }

        // Ensure room belongs to current campus and is bookable/available
        $room = Room::where('id', $roomId)
            ->forCampus(app('campus')->id)
            ->bookable()
            ->withStatus(Room::STATUS_AVAILABLE)
            ->first();

        if (! $room) {
            return Redirect::back()->with('error', 'Selected room is not available or not in the current campus.');
        }

        // Check schedule-based conflicts using offering schedule
        $scheduleDays = $courseOffering->schedule_days ?? [];
        $startTime = $courseOffering->schedule_time_start ? $courseOffering->schedule_time_start->format('H:i') : null;
        $endTime = $courseOffering->schedule_time_end ? $courseOffering->schedule_time_end->format('H:i') : null;

        $conflictExists = false;

        if (! empty($scheduleDays) && $startTime && $endTime) {
            // Map schedule days to MySQL WEEKDAY() indexes (Monday=0 ... Sunday=6)
            $dayToIndex = [
                'monday' => 0,
                'tuesday' => 1,
                'wednesday' => 2,
                'thursday' => 3,
                'friday' => 4,
                'saturday' => 5,
                'sunday' => 6,
            ];
            $weekdayIndexes = collect($scheduleDays)
                ->map(fn($d) => strtolower($d))
                ->map(fn($d) => $dayToIndex[$d] ?? null)
                ->filter(static fn($v) => $v !== null)
                ->values()
                ->all();

            if (! empty($weekdayIndexes)) {
                $conflictExists = ClassSession::where('room_id', $roomId)
                    ->where('status', '!=', 'cancelled')
                    ->whereIn(DB::raw('WEEKDAY(session_date)'), $weekdayIndexes)
                    ->where(function ($q) use ($startTime, $endTime) {
                        $q->where(function ($subQ) use ($startTime) {
                            // Existing session overlaps new start
                            $subQ->whereTime('start_time', '<=', $startTime)
                                ->whereTime('end_time', '>', $startTime);
                        })->orWhere(function ($subQ) use ($endTime) {
                            // Existing session overlaps new end
                            $subQ->whereTime('start_time', '<', $endTime)
                                ->whereTime('end_time', '>=', $endTime);
                        })->orWhere(function ($subQ) use ($startTime, $endTime) {
                            // Existing session fully within the new window
                            $subQ->whereTime('start_time', '>=', $startTime)
                                ->whereTime('end_time', '<=', $endTime);
                        });
                    })
                    ->exists();
            }
        } else {
            // Fallback: check conflicts on exact dates of this offering's sessions using first session's time window
            $firstSession = $courseOffering->classSessions()->orderBy('session_date')->first();
            if ($firstSession) {
                $dates = $courseOffering->classSessions()->pluck('session_date');
                $start = $firstSession->start_time->format('H:i');
                $end = $firstSession->end_time->format('H:i');

                $conflictExists = ClassSession::where('room_id', $roomId)
                    ->where('status', '!=', 'cancelled')
                    ->whereIn('session_date', $dates)
                    ->where(function ($q) use ($start, $end) {
                        $q->where(function ($subQ) use ($start) {
                            $subQ->whereTime('start_time', '<=', $start)
                                ->whereTime('end_time', '>', $start);
                        })->orWhere(function ($subQ) use ($end) {
                            $subQ->whereTime('start_time', '<', $end)
                                ->whereTime('end_time', '>=', $end);
                        })->orWhere(function ($subQ) use ($start, $end) {
                            $subQ->whereTime('start_time', '>=', $start)
                                ->whereTime('end_time', '<=', $end);
                        });
                    })
                    ->exists();
            }
        }

        if ($conflictExists) {
            return Redirect::back()->with('error', 'Selected room is not available for the course offering schedule.');
        }

        // Update all sessions to the new room
        DB::transaction(function () use ($courseOffering, $roomId) {
            $courseOffering->classSessions()->update(['room_id' => $roomId]);
        });

        return Redirect::back()->with('success', 'Room updated for all class sessions.');
    }

    /**
     * Search for students and check their eligibility for course registration
     */
    public function searchStudents(Request $request, CourseOffering $courseOffering)
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        $validated = $request->validate([
            'student_ids' => ['required', 'string'],
        ]);

        // Parse student IDs from whitespace-separated string
        $studentIds = preg_split('/\s+/', trim($validated['student_ids']));
        $studentIds = array_filter($studentIds); // Remove empty values
        $studentIds = array_unique($studentIds); // Remove duplicates

        // Load unit with courseOffering to check unit type
        $courseOffering->load('unit');
        $unit = $courseOffering->unit;
        $isEgcUnit = $unit && $unit->unit_type === 'egc';

        // Log for debugging
        Log::info('CourseOffering Unit Info', [
            'unit_id' => $unit?->id,
            'unit_code' => $unit?->code,
            'unit_type' => $unit?->unit_type,
            'unit_level' => $unit?->level,
            'is_egc_unit' => $isEgcUnit,
        ]);

        $results = [];

        foreach ($studentIds as $studentId) {
            $student = Student::where('student_id', $studentId)
                ->where('campus_id', app('campus')->id)
                ->with(['program:id,code,name', 'specialization:id,code,name'])
                ->first();

            $eligibilityInfo = [
                'student_id' => $studentId,
                'exists' => (bool) $student,
                'is_eligible' => false,
                'is_already_registered' => false,
                'eligibility_reasons' => [],
            ];

            if ($student) {
                $eligibilityInfo['student_data'] = [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'program' => $student->program ? [
                        'code' => $student->program->code,
                        'name' => $student->program->name,
                    ] : null,
                    'specialization' => $student->specialization ? [
                        'code' => $student->specialization->code,
                        'name' => $student->specialization->name,
                    ] : null,
                ];

                // Set major code from program or specialization
                $eligibilityInfo['major_code'] = $student->specialization?->code ?? $student->program?->code ?? 'N/A';

                // Check if already registered for this specific course offering
                $existingRegistration = CourseRegistration::where('student_id', $student->id)
                    ->where('course_offering_id', $courseOffering->id)
                    ->whereIn('registration_status', ['pending', 'registered', 'confirmed'])
                    ->exists();

                if ($existingRegistration) {
                    $eligibilityInfo['is_already_registered'] = true;
                    $eligibilityInfo['eligibility_reasons'][] = 'Already registered for this course offering';
                } else {
                    // Check if already registered for another offering of the same unit that is still active (not completed/cancelled)
                    $existingUnitRegistration = CourseRegistration::where('student_id', $student->id)
                        ->whereHas('courseOffering', function ($query) use ($courseOffering) {
                            $query->where('unit_id', $courseOffering->unit_id)
                                ->where('semester_id', $courseOffering->semester_id)
                                ->where('id', '!=', $courseOffering->id)
                                ->whereNotIn('course_status', ['completed', 'cancelled']); // Check if course is still active
                        })
                        ->with('courseOffering')
                        ->first();

                    if ($existingUnitRegistration) {
                        $eligibilityInfo['is_already_registered'] = true;
                        $sectionCode = $existingUnitRegistration->courseOffering->section_code;
                        $conflictMessage = $sectionCode
                            ? "Already registered for {$courseOffering->course_code} (Section: {$sectionCode}) that is still active"
                            : "Already registered for {$courseOffering->course_code} that is still active";
                        $eligibilityInfo['eligibility_reasons'][] = $conflictMessage;
                    } else {
                        // Check eligibility criteria only if not already registered for the unit
                        $isEligible = true;
                        $reasons = [];

                        // Validate student type based on unit type
                        if ($isEgcUnit) {
                            // Log student info for debugging
                            Log::info('EGC Unit - Checking Student', [
                                'student_id' => $student->student_id,
                                'student_status' => $student->status,
                                'gc_current_level' => $student->gc_current_level,
                                'unit_level' => $unit->level,
                            ]);

                            // EGC units: only allow intake_pre_uni_gc students
                            if ($student->status !== 'intake_pre_uni_gc') {
                                $isEligible = false;
                                $reasons[] = "EGC units require student type 'intake_pre_uni_gc' (current: '{$student->status}')";
                            } else {
                                // Check if unit has level defined (use is_null to allow level 0)
                                if (is_null($unit->level)) {
                                    $isEligible = false;
                                    $reasons[] = 'Unit level is not defined for this EGC unit';
                                    Log::error('EGC Unit Missing Level', [
                                        'unit_id' => $unit->id,
                                        'unit_code' => $unit->code,
                                    ]);
                                }
                                // Check if student has gc_current_level defined (use is_null to allow level 0)
                                elseif (is_null($student->gc_current_level)) {
                                    $isEligible = false;
                                    $reasons[] = "Student's GC level is not set";
                                    Log::error('Student Missing GC Level', [
                                        'student_id' => $student->student_id,
                                    ]);
                                }
                                // Check if student's current GC level matches unit level
                                elseif ($student->gc_current_level !== $unit->level) {
                                    $isEligible = false;
                                    $reasons[] = "Student's current GC level ({$student->gc_current_level}) does not match unit level ({$unit->level})";
                                    Log::warning('EGC Level Mismatch', [
                                        'student_id' => $student->student_id,
                                        'student_level' => $student->gc_current_level,
                                        'required_level' => $unit->level,
                                    ]);
                                }
                            }
                        } else {
                            // Non-EGC units: only allow intake_course students
                            if ($student->status !== 'intake_course') {
                                $isEligible = false;
                                $reasons[] = "This unit requires student type 'intake_course' (current: '{$student->status}')";
                            }
                        }

                        // Check if student is active (only if not already failed above validation)
                        if ($isEligible && ! $student->isActive()) {
                            $isEligible = false;
                            $reasons[] = "Student status is '{$student->status}' (must be 'active')";
                        }

                        // Check for academic holds (if method exists)
                        if ($isEligible && method_exists($student, 'hasActiveHolds') && $student->hasActiveHolds()) {
                            $isEligible = false;
                            $reasons[] = 'Student has active academic holds';
                        }

                        // Check course offering capacity
                        if ($isEligible && $courseOffering->isFull()) {
                            $isEligible = false;
                            $reasons[] = 'Course offering is at full capacity';
                        }

                        // Check if registration is open
                        if ($isEligible && ! $courseOffering->isRegistrationOpen()) {
                            $isEligible = false;
                            $reasons[] = 'Registration period is not currently open';
                        }

                        if ($isEligible) {
                            $reasons[] = 'Eligible for registration';
                        }

                        $eligibilityInfo['is_eligible'] = $isEligible;
                        $eligibilityInfo['eligibility_reasons'] = $reasons;
                    }
                }
            } else {
                $eligibilityInfo['eligibility_reasons'][] = 'Student ID not found in system';
                $eligibilityInfo['major_code'] = 'N/A';
            }

            $results[] = $eligibilityInfo;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'students' => $results,
            ],
        ]);
    }

    /**
     * Bulk register students to a course offering
     */
    public function bulkRegisterStudents(Request $request, CourseOffering $courseOffering)
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        // Validate course status - cannot register to completed/cancelled courses
        if (in_array($courseOffering->course_status, ['completed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot register students to a completed or cancelled course.',
            ], 422);
        }

        $validated = $request->validate([
            'student_ids' => ['required', 'array'],
            'student_ids.*' => ['required', 'string'],
        ]);

        // Load unit to check unit type
        $courseOffering->load('unit');
        $unit = $courseOffering->unit;
        $isEgcUnit = $unit && $unit->unit_type === 'egc';

        $studentIds = array_unique($validated['student_ids']);
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        try {
            DB::beginTransaction();

            foreach ($studentIds as $studentId) {
                $result = [
                    'student_id' => $studentId,
                    'success' => false,
                    'message' => '',
                ];

                try {
                    // Find student
                    $student = Student::where('student_id', $studentId)
                        ->where('campus_id', app('campus')->id)
                        ->first();

                    if (! $student) {
                        $result['message'] = 'Student not found';
                        $failureCount++;
                        $results[] = $result;

                        continue;
                    }

                    // Validate student type based on unit type
                    if ($isEgcUnit) {
                        // EGC units: only allow intake_pre_uni_gc students
                        if ($student->status !== 'intake_pre_uni_gc') {
                            $result['message'] = "EGC units require student type 'intake_pre_uni_gc' (current: '{$student->status}')";
                            $failureCount++;
                            $results[] = $result;

                            continue;
                        }

                        // Check if unit has level defined (use is_null to allow level 0)
                        if (is_null($unit->level)) {
                            $result['message'] = 'Unit level is not defined for this EGC unit';
                            $failureCount++;
                            $results[] = $result;
                            Log::error('EGC Unit Missing Level', [
                                'unit_id' => $unit->id,
                                'unit_code' => $unit->code,
                            ]);

                            continue;
                        }

                        // Check if student has gc_current_level defined (use is_null to allow level 0)
                        if (is_null($student->gc_current_level)) {
                            $result['message'] = "Student's GC level is not set";
                            $failureCount++;
                            $results[] = $result;
                            Log::error('Student Missing GC Level', [
                                'student_id' => $student->student_id,
                            ]);

                            continue;
                        }

                        // Check if student's current GC level matches unit level
                        if ($student->gc_current_level !== $unit->level) {
                            $result['message'] = "Student's current GC level ({$student->gc_current_level}) does not match unit level ({$unit->level})";
                            $failureCount++;
                            $results[] = $result;

                            continue;
                        }
                    } else {
                        // Non-EGC units: only allow intake_course students
                        if ($student->status !== 'intake_course') {
                            $result['message'] = "This unit requires student type 'intake_course' (current: '{$student->status}')";
                            $failureCount++;
                            $results[] = $result;

                            continue;
                        }
                    }

                    // Check if already registered for this specific course offering
                    $existingRegistration = CourseRegistration::where('student_id', $student->id)
                        ->where('course_offering_id', $courseOffering->id)
                        ->whereIn('registration_status', ['pending', 'registered', 'confirmed'])
                        ->exists();

                    if ($existingRegistration) {
                        $result['message'] = 'Already registered for this course';
                        $failureCount++;
                        $results[] = $result;

                        continue;
                    }

                    // Check if academic record already exists and force delete any soft-deleted ones
                    $existingAcademicRecord = \App\Models\AcademicRecord::withTrashed()
                        ->where('student_id', $student->id)
                        ->where('course_offering_id', $courseOffering->id)
                        ->first();

                    if ($existingAcademicRecord) {
                        // Force delete any existing record (including soft-deleted ones)
                        $existingAcademicRecord->forceDelete();
                    }

                    // Check if already registered for another offering of the same unit that is still active (not completed/cancelled)
                    $existingUnitRegistration = CourseRegistration::where('student_id', $student->id)
                        ->whereHas('courseOffering', function ($query) use ($courseOffering) {
                            $query->where('unit_id', $courseOffering->unit_id)
                                ->where('semester_id', $courseOffering->semester_id)
                                ->where('id', '!=', $courseOffering->id)
                                ->whereNotIn('course_status', ['completed', 'cancelled']); // Check if course is still active
                        })
                        ->with('courseOffering')
                        ->first();

                    if ($existingUnitRegistration) {
                        $sectionCode = $existingUnitRegistration->courseOffering->section_code;
                        $conflictMessage = $sectionCode
                            ? "Already registered for {$courseOffering->course_code} (Section: {$sectionCode}) that is still active"
                            : "Already registered for {$courseOffering->course_code} that is still active";
                        $result['message'] = $conflictMessage;
                        $failureCount++;
                        $results[] = $result;

                        continue;
                    }

                    // Check if student has program assigned
                    if (! $student->program_id) {
                        $result['message'] = 'Student is not assigned to any program';
                        $failureCount++;
                        $results[] = $result;

                        continue;
                    }

                    // Check eligibility
                    if (! $student->isActive()) {
                        $result['message'] = "Student status is '{$student->status}' (must be 'active')";
                        $failureCount++;
                        $results[] = $result;

                        continue;
                    }

                    // Check for academic holds (if method exists)
                    if (method_exists($student, 'hasActiveHolds') && $student->hasActiveHolds()) {
                        $result['message'] = 'Student has active academic holds';
                        $failureCount++;
                        $results[] = $result;

                        continue;
                    }

                    // Check course offering capacity (allow admin override)
                    if ($courseOffering->isFull()) {
                        Log::warning("Enrolling student {$studentId} in full course {$courseOffering->id} via admin override");
                    }

                    // Check previous attempts from academic_records to determine if this is a retake
                    $previousAttempts = \App\Models\AcademicRecord::where('student_id', $student->id)
                        ->where('unit_id', $courseOffering->unit_id)
                        ->orderBy('attempt_number', 'desc')
                        ->get();

                    $isRetake = $previousAttempts->count() > 0;
                    $attemptNumber = $isRetake ? $previousAttempts->first()->attempt_number + 1 : 1;
                    $originalRecordId = $isRetake ? $previousAttempts->first()->id : null;

                    // Create course registration (only for tracking enrollment)
                    $registration = CourseRegistration::create([
                        'student_id' => $student->id,
                        'course_offering_id' => $courseOffering->id,
                        'semester_id' => $courseOffering->semester_id,
                        'registration_status' => 'confirmed',
                        'registration_date' => now(),
                        'registration_method' => 'admin_override',
                        'credit_hours' => $courseOffering->unit->credit_points ?? 3,
                        'attempt_number' => 1, // Keep for backward compatibility, but don't use
                        'is_retake' => false,  // Keep for backward compatibility, but don't use
                        'retake_fee' => 0.00,  // Keep for backward compatibility, but don't use
                        'is_retake_paid' => 'no', // Keep for backward compatibility, but don't use
                    ]);

                    // Create academic record immediately with proper retake tracking
                    \App\Models\AcademicRecord::create([
                        'student_id' => $student->id,
                        'course_offering_id' => $courseOffering->id,
                        'semester_id' => $courseOffering->semester_id,
                        'unit_id' => $courseOffering->unit_id,
                        'program_id' => $student->program_id,
                        'campus_id' => app('campus')->id,

                        // Academic tracking
                        'is_repeat_course' => $isRetake,
                        'attempt_number' => $attemptNumber,
                        'original_record_id' => $originalRecordId,

                        // Status & dates
                        'grade_status' => 'in_progress',
                        'completion_status' => 'in_progress',
                        'enrollment_date' => now(),

                        // Credit info
                        'credit_hours' => $courseOffering->unit->credit_points ?? 3,
                        'credit_hours_earned' => 0.00,

                        // Instructor
                        'instructor_id' => $courseOffering->lecture_id,

                        // Grades (null when newly registered)
                        'final_percentage' => null,
                        'final_letter_grade' => null,
                        'grade_points' => null,
                        'quality_points' => null,
                    ]);

                    // Update course offering enrollment count
                    $courseOffering->increment('current_enrollment');

                    // Create invoice item for course fee (EGC or retake) - MANDATORY
                    $this->createCourseFeeInvoiceItem($student, $courseOffering, $registration, $isRetake);

                    $result['success'] = true;
                    $result['message'] = 'Successfully registered';
                    $successCount++;
                } catch (\Exception $e) {
                    // Log detailed error
                    Log::error("Failed to register student {$studentId}: " . $e->getMessage(), [
                        'student_id' => $studentId,
                        'course_offering_id' => $courseOffering->id,
                        'exception' => get_class($e),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    // Parse error message for better user experience
                    $errorMessage = $this->parseRegistrationError($e);

                    $result['message'] = $errorMessage;
                    $failureCount++;
                }

                $results[] = $result;
            }

            DB::commit();

            // Log bulk registration summary
            Log::info('Bulk registration completed', [
                'course_offering_id' => $courseOffering->id,
                'course_code' => $courseOffering->course_code,
                'total_students' => count($studentIds),
                'successful' => $successCount,
                'failed' => $failureCount,
                'admin_user_id' => Auth::id(),
            ]);

            $message = "Registration completed. {$successCount} successful, {$failureCount} failed.";

            return response()->json([
                'success' => true,
                'data' => [
                    'successful_registrations' => $successCount,
                    'failed_registrations' => $failureCount,
                    'results' => $results,
                ],
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk registration failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Bulk registration failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get course offering statistics
     */
    public function statistics(Request $request)
    {
        $semesterId = $request->semester_id;

        $query = CourseOffering::query()->where('campus_id', app('campus')->id);

        if ($semesterId && $semesterId !== 'all') {
            $query->where('semester_id', $semesterId);
        }

        $stats = [
            'total_offerings' => $query->count(),
            'active_offerings' => (clone $query)->where('is_active', true)->where('enrollment_status', 'open')->count(),
            'full_offerings' => (clone $query)->whereRaw('current_enrollment >= max_capacity')->count(),
            'cancelled_offerings' => (clone $query)->where('enrollment_status', 'cancelled')->count(),
            'total_enrollment' => (clone $query)->sum('current_enrollment'),
            'total_capacity' => (clone $query)->sum('max_capacity'),
        ];

        $stats['enrollment_rate'] = $stats['total_capacity'] > 0
            ? round(($stats['total_enrollment'] / $stats['total_capacity']) * 100, 2)
            : 0;

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Duplicate a course offering without the assigned lecturer
     */
    public function duplicate(CourseOffering $courseOffering): RedirectResponse
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        try {
            // Get all attributes except the ones we want to exclude or modify
            $attributes = $courseOffering->getAttributes();

            // Remove attributes that should not be duplicated
            unset(
                $attributes['id'],
                $attributes['lecture_id'], // Exclude assigned lecturer as requested
                $attributes['current_enrollment'], // Reset enrollment
                $attributes['current_waitlist'], // Reset waitlist
                $attributes['created_at'],
                $attributes['updated_at'],
                $attributes['deleted_at']
            );

            // Reset enrollment counters
            $attributes['current_enrollment'] = 0;
            $attributes['current_waitlist'] = 0;

            // Modify section code if it exists to avoid duplicates
            if ($courseOffering->section_code) {
                $baseSectionCode = $courseOffering->section_code;
                $counter = 1;

                // Find a unique section code
                do {
                    $newSectionCode = $baseSectionCode . '_copy' . ($counter > 1 ? $counter : '');
                    $exists = CourseOffering::where('semester_id', $courseOffering->semester_id)
                        ->where('unit_id', $courseOffering->unit_id)
                        ->where('campus_id', $courseOffering->campus_id)
                        ->where('section_code', $newSectionCode)
                        ->exists();
                    $counter++;
                } while ($exists && $counter <= 100); // Prevent infinite loop

                $attributes['section_code'] = $newSectionCode;
            }

            // Create the duplicated course offering
            $duplicatedOffering = CourseOffering::create($attributes);

            return Redirect::route(CourseOfferingRoutes::INDEX)
                ->with('success', 'Course offering duplicated successfully. Please assign an instructor.');
        } catch (\Exception $e) {
            Log::error('Failed to duplicate course offering: ' . $e->getMessage());

            return Redirect::back()
                ->with('error', 'Failed to duplicate course offering: ' . $e->getMessage());
        }
    }

    /**
     * Show the split course offering form
     */
    public function showSplit(CourseOffering $courseOffering): Response|RedirectResponse
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        // Check if this course offering can be split
        if ($courseOffering->section_code) {
            return Redirect::back()
                ->with('error', 'This course offering is already a section and cannot be split further.');
        }

        if ($courseOffering->current_enrollment === 0) {
            return Redirect::back()
                ->with('error', 'Cannot split a course offering with no enrolled students.');
        }

        $courseOffering->load([
            'semester',
            'unit',
            'lecture',
            'courseRegistrations.student',
        ]);

        // Get all enrolled students
        $enrolledStudents = $courseOffering->courseRegistrations
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->map(function ($registration) {
                return [
                    'id' => $registration->student->id,
                    'student_id' => $registration->student->student_id,
                    'full_name' => $registration->student->full_name,
                    'email' => $registration->student->email,
                    'registration_id' => $registration->id,
                ];
            })
            ->values();

        // Get available lectures
        $lectures = Lecture::active()
            ->availableForAssignment()
            ->orderByName()
            ->get(['id', 'first_name', 'last_name', 'email', 'academic_rank']);

        return Inertia::render('course-offerings/Split', [
            'courseOffering' => $courseOffering,
            'enrolledStudents' => $enrolledStudents,
            'lectures' => $lectures,
        ]);
    }

    /**
     * Perform the course offering split
     */
    public function performSplit(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        $request->validate([
            'number_of_sections' => 'required|integer|min:2|max:10',
            'assignment_mode' => 'required|in:equal,custom',
            'sections' => 'required|array',
            'sections.*.section_code' => 'required|string|max:10',
            'sections.*.max_capacity' => 'required|integer|min:1',
            'sections.*.lecture_id' => ['nullable', function ($attribute, $value, $fail) {
                if ($value && $value !== 'none' && ! Lecture::find($value)) {
                    $fail('The selected lecture does not exist.');
                }
            }],
            'sections.*.location' => 'nullable|string|max:255',
            'sections.*.student_ids' => 'required|array',
            'sections.*.student_ids.*' => 'exists:students,id',
        ]);

        // Check if this course offering can be split
        if ($courseOffering->section_code) {
            return Redirect::back()
                ->with('error', 'This course offering is already a section and cannot be split further.');
        }

        if ($courseOffering->current_enrollment === 0) {
            return Redirect::back()
                ->with('error', 'Cannot split a course offering with no enrolled students.');
        }

        $sections = $request->sections;
        $totalStudentsAssigned = collect($sections)->sum(fn($section) => count($section['student_ids']));

        if ($totalStudentsAssigned !== $courseOffering->current_enrollment) {
            return Redirect::back()
                ->with('error', 'All enrolled students must be assigned to sections.');
        }

        try {
            DB::beginTransaction();

            $newOfferings = [];
            $registrationUpdates = [];

            foreach ($sections as $sectionData) {
                // Handle lecture assignment - null if 'none' is selected
                $lectureId = null;
                if (isset($sectionData['lecture_id']) && $sectionData['lecture_id'] !== 'none') {
                    $lectureId = (int) $sectionData['lecture_id'];
                }

                // Create new course offering for this section
                $newOffering = CourseOffering::create([
                    'campus_id' => app('campus')->id,
                    'semester_id' => $courseOffering->semester_id,
                    'unit_id' => $courseOffering->unit_id,
                    'lecture_id' => $lectureId,
                    'section_code' => $sectionData['section_code'],
                    'max_capacity' => $sectionData['max_capacity'],
                    'current_enrollment' => count($sectionData['student_ids']),
                    'waitlist_capacity' => $courseOffering->waitlist_capacity,
                    'current_waitlist' => 0,
                    'delivery_mode' => $courseOffering->delivery_mode,
                    'schedule_days' => $courseOffering->schedule_days,
                    'schedule_time_start' => $courseOffering->schedule_time_start,
                    'schedule_time_end' => $courseOffering->schedule_time_end,
                    'location' => $sectionData['location'] ?? $courseOffering->location,
                    'is_active' => $courseOffering->is_active,
                    'enrollment_status' => $courseOffering->enrollment_status,
                    'registration_start_date' => $courseOffering->registration_start_date,
                    'registration_end_date' => $courseOffering->registration_end_date,
                    'special_requirements' => $courseOffering->special_requirements,
                    'notes' => $courseOffering->notes,
                ]);

                $newOfferings[] = $newOffering;

                // Prepare registration updates for this section
                foreach ($sectionData['student_ids'] as $studentId) {
                    $registrationUpdates[] = [
                        'student_id' => $studentId,
                        'new_course_offering_id' => $newOffering->id,
                    ];
                }
            }

            // Update course registrations to point to new sections
            // First, move active students to their assigned sections
            foreach ($registrationUpdates as $update) {
                CourseRegistration::where('course_offering_id', $courseOffering->id)
                    ->where('student_id', $update['student_id'])
                    ->whereIn('registration_status', ['registered', 'confirmed'])
                    ->update(['course_offering_id' => $update['new_course_offering_id']]);
            }

            // Move any remaining registrations (dropped, withdrawn, etc.) to the first section
            // to maintain historical records
            $firstSectionId = $newOfferings[0]->id;
            CourseRegistration::where('course_offering_id', $courseOffering->id)
                ->update(['course_offering_id' => $firstSectionId]);

            // Now we can safely delete the original course offering since all registrations have been moved
            $courseOffering->delete();

            DB::commit();

            return Redirect::route(CourseOfferingRoutes::INDEX)
                ->with('success', 'Course offering successfully split into ' . count($sections) . ' sections. The original course offering has been deleted.');
        } catch (\Exception $e) {
            DB::rollBack();

            return Redirect::back()
                ->with('error', 'Failed to split course offering: ' . $e->getMessage());
        }
    }

    /**
     * Check if all course offerings for a semester have instructors assigned
     */
    public function checkInstructorAssignments(Request $request)
    {
        $request->validate([
            'semester_id' => 'required|exists:semesters,id',
        ]);

        $semesterId = $request->semester_id;

        // Get all active course offerings without lectures
        $offeringsWithoutInstructors = CourseOffering::with(['unit', 'semester'])
            ->where('campus_id', app('campus')->id)
            ->where('semester_id', $semesterId)
            ->where('is_active', true)
            ->whereNull('lecture_id')
            ->get();

        // Get semester to check if classes have started
        $semester = Semester::find($semesterId);
        $classesStarted = $semester && $semester->start_date <= now();

        return response()->json([
            'success' => true,
            'data' => [
                'semester_id' => $semesterId,
                'semester_name' => $semester->name ?? 'Unknown',
                'classes_started' => $classesStarted,
                'offerings_without_instructors' => $offeringsWithoutInstructors->count(),
                'unassigned_offerings' => $offeringsWithoutInstructors->map(function ($offering) {
                    return [
                        'id' => $offering->id,
                        'course_code' => $offering->course_code,
                        'course_title' => $offering->course_title,
                        'section_code' => $offering->section_code,
                        'current_enrollment' => $offering->current_enrollment,
                        'max_capacity' => $offering->max_capacity,
                    ];
                }),
                'is_ready_for_classes' => $offeringsWithoutInstructors->count() === 0,
                'warning_message' => $offeringsWithoutInstructors->count() > 0 && $classesStarted
                    ? 'Classes have started but some course offerings do not have assigned instructors!'
                    : null,
            ],
        ]);
    }

    /**
     * Bulk assign instructors to course offerings
     */
    public function bulkAssignLectures(Request $request): RedirectResponse
    {
        $request->validate([
            'assignments' => 'required|array',
            'assignments.*.course_offering_id' => 'required|exists:course_offerings,id',
            'assignments.*.lecture_id' => 'required|exists:lectures,id',
        ]);

        try {
            DB::beginTransaction();

            $assignmentsCount = 0;
            foreach ($request->assignments as $assignment) {
                $courseOffering = CourseOffering::where('id', $assignment['course_offering_id'])
                    ->where('campus_id', app('campus')->id)
                    ->first();
                if ($courseOffering && ! $courseOffering->lecture_id) {
                    $courseOffering->update(['lecture_id' => $assignment['lecture_id']]);
                    $assignmentsCount++;
                }
            }

            DB::commit();

            return Redirect::back()
                ->with('success', "Successfully assigned lectures to {$assignmentsCount} course offerings.");
        } catch (\Exception $e) {
            DB::rollBack();

            return Redirect::back()
                ->with('error', 'Failed to assign lectures: ' . $e->getMessage());
        }
    }

    /**
     * Delete individual student registration from a course offering
     */
    public function deleteStudentRegistration(Request $request, CourseOffering $courseOffering)
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        $request->validate([
            'registration_id' => 'required|exists:course_registrations,id',
        ]);

        try {
            DB::beginTransaction();

            $registration = CourseRegistration::where('id', $request->registration_id)
                ->where('course_offering_id', $courseOffering->id)
                ->with('student')
                ->first();

            if (! $registration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration not found or does not belong to this course offering.',
                ], 404);
            }

            $studentName = $registration->student->full_name ?? 'Unknown Student';
            $studentId = $registration->student->student_id ?? 'Unknown ID';
            $studentDbId = $registration->student->id;

            // Delete invoice items (must use model instance to trigger events)
            $invoiceItems = \App\Models\InvoiceItem::where('reference_id', $registration->id)
                ->where('reference_type', CourseRegistration::class)
                ->get();

            foreach ($invoiceItems as $item) {
                $item->delete(); // Triggers model event → recalculateTotals()
            }

            // Force delete ALL academic records for this student + offering (including soft-deleted ones)
            // After removing unique constraints, there might be multiple records
            $academicRecords = \App\Models\AcademicRecord::withTrashed()
                ->where('course_offering_id', $courseOffering->id)
                ->where('student_id', $studentDbId)
                ->get();

            foreach ($academicRecords as $record) {
                $record->forceDelete();
            }

            // Delete the course registration
            $registration->delete();

            // Update course offering enrollment count
            $courseOffering->decrement('current_enrollment');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully removed {$studentName} ({$studentId}) from the course.",
                'data' => [
                    'deleted_registration_id' => $request->registration_id,
                    'student_name' => $studentName,
                    'student_id' => $studentId,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete student registration: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove student from course: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk update registration status for students
     */
    public function bulkUpdateRegistrationStatus(Request $request, CourseOffering $courseOffering)
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        $request->validate([
            'from_status' => 'required|in:registered,confirmed,dropped,withdrawn,completed',
            'to_status' => 'required|in:registered,confirmed,dropped,withdrawn,completed',
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        try {
            DB::beginTransaction();

            $updatedCount = CourseRegistration::where('course_offering_id', $courseOffering->id)
                ->where('registration_status', $request->from_status)
                ->whereIn('student_id', $request->student_ids)
                ->update([
                    'registration_status' => $request->to_status,
                    'updated_at' => now(),
                ]);

            // Update course offering enrollment counts if needed
            if (
                in_array($request->from_status, ['registered', 'confirmed']) &&
                ! in_array($request->to_status, ['registered', 'confirmed'])
            ) {
                // Students are being removed from active status
                $courseOffering->decrement('current_enrollment', $updatedCount);
            } elseif (
                ! in_array($request->from_status, ['registered', 'confirmed']) &&
                in_array($request->to_status, ['registered', 'confirmed'])
            ) {
                // Students are being added to active status
                $courseOffering->increment('current_enrollment', $updatedCount);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$updatedCount} student registration(s) from {$request->from_status} to {$request->to_status}.",
                'data' => [
                    'updated_count' => $updatedCount,
                    'from_status' => $request->from_status,
                    'to_status' => $request->to_status,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update registration status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update course status
     */
    public function updateCourseStatus(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
        // Ensure the course offering belongs to current campus
        if ($courseOffering->campus_id !== app('campus')->id) {
            abort(404);
        }

        // Check if course is already completed - cannot modify
        if ($courseOffering->course_status === 'completed') {
            return Redirect::back()
                ->with('error', 'Cannot change status of a completed course.');
        }

        $request->validate([
            'course_status' => ['required', 'in:not_started,in_progress,completed,cancelled'],
        ]);

        $oldStatus = $courseOffering->course_status ?? 'not_started';
        $newStatus = $request->course_status;

        // If marking as completed, run finalization process
        if ($newStatus === 'completed') {
            try {
                DB::beginTransaction();

                // Load necessary relationships
                $courseOffering->load(['unit', 'semester']);

                // Finalize course with all validations and EGC progression
                $result = app(\App\Services\CourseCompletionService::class)->finalizeCourse($courseOffering);

                // Update course offering status
                $courseOffering->update(['course_status' => 'completed']);

                DB::commit();

                // Prepare success message with details
                $message = "Course '{$result['course_code']}' marked as completed successfully.";

                if ($result['egc_progression']['processed']) {
                    // EGC course message with detailed breakdown
                    $egc = $result['egc_progression'];
                    $message .= "<br><br><strong>EGC Summary:</strong> {$egc['total_students']} student(s) processed";

                    // Show progressed students
                    if (count($egc['progressed']) > 0) {
                        $message .= "<br>✅ <strong>" . count($egc['progressed']) . " Progressed:</strong>";
                        foreach (array_slice($egc['progressed'], 0, 5) as $prog) {
                            $message .= "<br>&nbsp;&nbsp;• {$prog['student_id']} ({$prog['student_name']}): Level {$prog['from_level']} → {$prog['to_level']}";
                        }
                        if (count($egc['progressed']) > 5) {
                            $message .= "<br>&nbsp;&nbsp;• +" . (count($egc['progressed']) - 5) . " more...";
                        }
                    }

                    // Show failed students with reasons
                    if (count($egc['failed_students']) > 0) {
                        $message .= "<br>❌ <strong>" . count($egc['failed_students']) . " Failed:</strong>";
                        foreach (array_slice($egc['failed_students'], 0, 5) as $fail) {
                            $reason = $fail['action'];
                            $message .= "<br>&nbsp;&nbsp;• {$fail['student_id']} ({$fail['student_name']}): Grade {$fail['grade']} - {$reason}";
                        }
                        if (count($egc['failed_students']) > 5) {
                            $message .= "<br>&nbsp;&nbsp;• +" . (count($egc['failed_students']) - 5) . " more...";
                        }
                    }

                    // Show level mismatch warnings
                    if (count($egc['warnings']) > 0) {
                        $message .= "<br>⚠️ <strong>" . count($egc['warnings']) . " Warnings:</strong>";
                        foreach (array_slice($egc['warnings'], 0, 5) as $warn) {
                            $studentLevel = $warn['student_level'] ?? 'N/A';
                            $unitLevel = $warn['unit_level'] ?? 'N/A';

                            // Check if this is a level mismatch warning or status warning
                            if (isset($warn['reason']) && strpos($warn['reason'], 'Level mismatch') !== false) {
                                $message .= "<br>&nbsp;&nbsp;• {$warn['student_id']} ({$warn['student_name']}): Student at Level {$studentLevel}, passed Level {$unitLevel} course - Grade recorded but NOT progressed";
                            } elseif (isset($warn['reason'])) {
                                $message .= "<br>&nbsp;&nbsp;• {$warn['student_id']} ({$warn['student_name']}): {$warn['reason']}";
                            } else {
                                $message .= "<br>&nbsp;&nbsp;• {$warn['student_id']} ({$warn['student_name']}): " . ($warn['reason'] ?? 'Warning');
                            }
                        }
                        if (count($egc['warnings']) > 5) {
                            $message .= "<br>&nbsp;&nbsp;• +" . (count($egc['warnings']) - 5) . " more...";
                        }
                    }
                } elseif ($result['non_egc_result']) {
                    // Non-EGC course message
                    $nonEgc = $result['non_egc_result'];
                    $totalStudents = $nonEgc['passed'] + $nonEgc['failed'];
                    $message .= "<br><br><strong>Summary:</strong> {$totalStudents} student(s) processed";

                    if ($nonEgc['passed'] > 0) {
                        $message .= "<br>✅ {$nonEgc['passed']} passed";
                    }

                    if ($nonEgc['failed'] > 0) {
                        $message .= "<br>❌ {$nonEgc['failed']} failed";
                    }
                }

                Log::info('Course status updated to completed', [
                    'course_offering_id' => $courseOffering->id,
                    'course_code' => $courseOffering->course_code,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'egc_result' => $result['egc_progression'],
                ]);

                return Redirect::back()->with('success', $message);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Failed to complete course: ' . $e->getMessage(), [
                    'course_offering_id' => $courseOffering->id,
                    'course_code' => $courseOffering->course_code,
                ]);

                // Return with error status for Inertia to trigger onError
                return Redirect::back()
                    ->with('error', 'Failed to complete course: ' . $e->getMessage())
                    ->withErrors(['course_status' => 'Failed to complete course: ' . $e->getMessage()]);
            }
        } else {
            // Simple status update for non-completed statuses
            try {
                $courseOffering->update(['course_status' => $newStatus]);

                Log::info('Course status updated', [
                    'course_offering_id' => $courseOffering->id,
                    'course_code' => $courseOffering->course_code,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);

                return Redirect::back()
                    ->with('success', "Course status updated to '{$newStatus}' successfully.");
            } catch (\Exception $e) {
                Log::error('Failed to update course status: ' . $e->getMessage());

                return Redirect::back()
                    ->with('error', 'Failed to update course status: ' . $e->getMessage());
            }
        }
    }

    /**
     * Create invoice item for course registration fee (EGC base_fee or retake_fee)
     *
     * @param  Student  $student
     * @param  CourseOffering  $courseOffering
     * @param  CourseRegistration  $registration
     * @param  bool  $isRetake
     * @return void
     */
    private function createCourseFeeInvoiceItem(
        Student $student,
        CourseOffering $courseOffering,
        CourseRegistration $registration,
        bool $isRetake
    ): void {
        $unit = $courseOffering->unit;

        // Determine if fee should be charged
        $shouldCharge = false;
        $itemType = null;
        $fee = 0;
        $description = '';

        if ($unit->unit_type === 'egc') {
            // EGC courses: charge base_fee for first attempt, retake_fee for retakes
            if ($isRetake) {
                $shouldCharge = true;
                $itemType = 'retake';
                $fee = $unit->retake_fee ?? 0;
                $description = "Retake Fee: {$unit->code} - {$unit->name} (Attempt #" . ($isRetake ? '2+' : '1') . ')';
            } else {
                // First time EGC - charge base_fee
                $shouldCharge = true;
                $itemType = 'egc';
                $fee = $unit->base_fee ?? 0;
                $description = "EGC Course Fee: {$unit->code} - {$unit->name}";
            }
        } elseif ($isRetake) {
            // Non-EGC retake - charge retake_fee
            $shouldCharge = true;
            $itemType = 'retake';
            $fee = $unit->retake_fee ?? 0;
            $description = "Retake Fee: {$unit->code} - {$unit->name}";
        }
        // Non-EGC first time - no charge

        if (! $shouldCharge || $fee <= 0) {
            Log::info('No invoice item created', [
                'student_id' => $student->student_id,
                'unit_code' => $unit->code,
                'unit_type' => $unit->unit_type,
                'is_retake' => $isRetake,
                'reason' => $fee <= 0 ? 'Fee is zero or null' : 'No charge required',
            ]);

            return;
        }

        // Find or create student invoice for current semester
        $invoice = $this->findOrCreateStudentInvoice($student, $courseOffering->semester_id);

        // Create invoice item
        $invoiceItem = \App\Models\InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_type' => $itemType,
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $fee,
            'total_price' => $fee, // Will be auto-calculated by model
            'paid_amount' => 0.00,
            'reference_id' => $registration->id,
            'reference_type' => CourseRegistration::class,
        ]);

        // Invoice totals will be recalculated automatically via model events
        // Status will be updated to 'partial' if needed

        Log::info('Invoice item created for course registration', [
            'student_id' => $student->student_id,
            'invoice_id' => $invoice->id,
            'invoice_item_id' => $invoiceItem->id,
            'item_type' => $itemType,
            'unit_code' => $unit->code,
            'fee' => $fee,
            'is_retake' => $isRetake,
        ]);
    }

    /**
     * Find or create student invoice for given semester
     *
     * @param  Student  $student
     * @param  int  $semesterId
     * @return \App\Models\StudentInvoice
     */
    private function findOrCreateStudentInvoice(Student $student, int $semesterId): \App\Models\StudentInvoice
    {
        // Try to find existing invoice for student in this semester
        $invoice = \App\Models\StudentInvoice::where('student_id', $student->id)
            ->where('semester_id', $semesterId)
            ->first();

        if ($invoice) {
            return $invoice;
        }

        // No invoice found, create new one
        // First, find or create billing cycle for this semester
        $billingCycle = \App\Models\BillingCycle::where('semester_id', $semesterId)
            ->first();

        if (! $billingCycle) {
            // Create a default billing cycle if none exists
            $semester = Semester::find($semesterId);
            $billingCycle = \App\Models\BillingCycle::create([
                'semester_id' => $semesterId,
                'name' => 'Default Billing Cycle - ' . $semester->name,
                'start_date' => $semester->start_date,
                'end_date' => $semester->end_date,
                'due_date' => $semester->end_date,
                'status' => 'active',
            ]);

            Log::info('Created default billing cycle', [
                'billing_cycle_id' => $billingCycle->id,
                'semester_id' => $semesterId,
            ]);
        }

        // Generate unique invoice number
        $invoiceNumber = $this->generateInvoiceNumber($student, $semesterId);

        // Create new invoice
        $invoice = \App\Models\StudentInvoice::create([
            'invoice_number' => $invoiceNumber,
            'student_id' => $student->id,
            'billing_cycle_id' => $billingCycle->id,
            'semester_id' => $semesterId,
            'subtotal' => 0,
            'discount_total' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'status' => 'pending',
            'due_date' => $billingCycle->due_date,
        ]);

        Log::info('Created new student invoice', [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoiceNumber,
            'student_id' => $student->student_id,
            'semester_id' => $semesterId,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        return $invoice;
    }

    /**
     * Generate unique invoice number for student and semester
     *
     * @param  Student  $student
     * @param  int  $semesterId
     * @return string
     */
    private function generateInvoiceNumber(Student $student, int $semesterId): string
    {
        $semester = Semester::find($semesterId);
        $semesterCode = $semester ? $semester->code : 'SEM';

        // Format: INV-{SEMESTER_CODE}-{STUDENT_ID}-{TIMESTAMP}
        // Example: INV-FALL2025-S001-20251027
        return 'INV-' . $semesterCode . '-' . $student->student_id . '-' . now()->format('YmdHis');
    }

    /**
     * Parse registration error to user-friendly message
     *
     * @param  \Exception  $e
     * @return string
     */
    private function parseRegistrationError(\Exception $e): string
    {
        $message = $e->getMessage();

        // Duplicate academic record
        if (str_contains($message, 'unique_student_course_offering')) {
            return 'Student đã có academic record cho lớp này. Không thể đăng ký lại cùng 1 lớp.';
        }

        // Duplicate course registration
        if (str_contains($message, 'Duplicate entry') && str_contains($message, 'course_registrations')) {
            return 'Student đã đăng ký lớp này rồi.';
        }

        // Invoice creation failed
        if (str_contains($message, 'invoice') || str_contains($message, 'billing')) {
            return 'Không thể tạo hóa đơn học phí: ' . $message;
        }

        // Foreign key constraint
        if (str_contains($message, 'foreign key constraint')) {
            return 'Lỗi ràng buộc dữ liệu: Vui lòng kiểm tra thông tin student/program/semester.';
        }

        // Default: return original message
        return 'Đăng ký thất bại: ' . $message;
    }
}
