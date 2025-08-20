<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Constants\CourseOfferingRoutes;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseOfferingRequest;
use App\Http\Requests\UpdateCourseOfferingRequest;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\ClassSession;
use App\Models\Lecture;
use App\Models\Room;
use App\Models\Semester;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CourseOfferingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:view_course_offering')->only(['index', 'show']);
        $this->middleware('can:create_course_offering')->only(['create', 'store']);
        // $this->middleware('can:edit_course_offering')->only(['edit', 'update']);
        // $this->middleware('can:delete_course_offering')->only(['destroy']);
        // $this->middleware('can:manage_course_offering')->only(['bulkDelete', 'toggleStatus', 'showSplit', 'performSplit']);
    }

    /**
     * Display a listing of course offerings
     */
    public function index(Request $request): Response
    {
        $query = CourseOffering::with(['semester', 'curriculumUnit', 'lecture'])
            ->where('campus_id', app('campus')->id)
            ->orderBy('semester_id', 'desc')
            ->orderBy('section_code');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('section_code', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('curriculumUnit.unit', function ($unitQuery) use ($search) {
                        $unitQuery->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('semester_id') && $request->semester_id !== 'all') {
            $query->where('semester_id', $request->semester_id);
        }

        if ($request->filled('enrollment_status') && $request->enrollment_status !== 'all') {
            $query->where('enrollment_status', $request->enrollment_status);
        }

        if ($request->filled('delivery_mode') && $request->delivery_mode !== 'all') {
            $query->where('delivery_mode', $request->delivery_mode);
        }

        $courseOfferings = $query->paginate(15)->withQueryString();

        // Get filter options
        $semesters = Semester::orderBy('start_date', 'desc')->get(['id', 'name', 'code']);
        return Inertia::render('course-offerings/Index', [
            'courseOfferings' => $courseOfferings,
            'filters' => $request->only(['search', 'semester_id', 'enrollment_status', 'delivery_mode']),
            'semesters' => $semesters,
            'enrollmentStatusOptions' => [
                ['value' => 'open', 'label' => 'Open'],
                ['value' => 'closed', 'label' => 'Closed'],
                ['value' => 'waitlist_only', 'label' => 'Waitlist Only'],
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

        // Get curriculum units that should be offered in the active semester
        // The system calculates the current semester_number for each curriculum version based on:
        // 1. How many semesters have elapsed since the curriculum version started
        // 2. Each curriculum version's effective starting semester
        // This ensures proper academic progression for both new and continuing students

        // Get all curriculum versions with their starting semesters
        $allCurriculumVersions = \App\Models\CurriculumVersion::with(['curriculumUnits.unit', 'effectiveFromSemester'])
            ->get();

        // Get all semesters ordered by start date to calculate progression
        $allSemesters = Semester::orderBy('start_date')->get(['id', 'start_date']);

        // Create a mapping of semester_id to its sequential position
        $semesterSequence = $allSemesters->pluck('id')->flip();

        $availableUnits = collect();

        foreach ($allCurriculumVersions as $curriculumVersion) {
            // Get the starting semester position for this curriculum version
            $startingSemesterPosition = $semesterSequence[$curriculumVersion->semester_id] ?? null;
            $currentSemesterPosition = $semesterSequence[$activeSemester->id] ?? null;

            if ($startingSemesterPosition === null || $currentSemesterPosition === null) {
                continue;
            }

            // Calculate how many semesters have passed since this curriculum started
            $elapsedSemesters = $currentSemesterPosition - $startingSemesterPosition;

            // If curriculum hasn't started yet, skip it
            if ($elapsedSemesters < 0) {
                continue;
            }

            // Calculate the current semester number for this curriculum version
            // Semester 1 starts when elapsedSemesters = 0, Semester 2 when elapsedSemesters = 1, etc.
            $currentSemesterNumber = $elapsedSemesters + 1;

            // Get units that should be offered in the current semester for this curriculum version
            $currentSemesterUnits = $curriculumVersion->curriculumUnits()
                ->where('semester_number', $currentSemesterNumber)
                ->with('unit')
                ->get();

            foreach ($currentSemesterUnits as $curriculumUnit) {
                $availableUnits->push([
                    'curriculum_unit_id' => $curriculumUnit->id,
                    'unit_id' => $curriculumUnit->unit->id,
                    'code' => $curriculumUnit->unit->code,
                    'name' => $curriculumUnit->unit->name,
                    'credit_points' => $curriculumUnit->unit->credit_points,
                    'year_level' => $curriculumUnit->year_level,
                    'semester_number' => $curriculumUnit->semester_number,
                    'curriculum_version_id' => $curriculumVersion->id,
                    'curriculum_start_semester' => $curriculumVersion->effectiveFromSemester->name ?? 'Unknown',
                    'elapsed_semesters' => $elapsedSemesters,
                    'current_semester_in_curriculum' => $currentSemesterNumber,
                    'source' => $elapsedSemesters === 0 ? 'new_curriculum' : 'continuing_curriculum',
                ]);
            }
        }

        // Add common curriculum units that are available regardless of semester progression
        // These are foundational units with scope = 'common' and semester_number = null
        $commonUnits = \App\Models\CurriculumUnit::with(['unit', 'curriculumVersion.effectiveFromSemester'])
            ->where('unit_scope', 'common')
            ->whereNull('semester_number')
            ->get();

        foreach ($commonUnits as $curriculumUnit) {
            $availableUnits->push([
                'curriculum_unit_id' => $curriculumUnit->id,
                'unit_id' => $curriculumUnit->unit->id,
                'code' => $curriculumUnit->unit->code,
                'name' => $curriculumUnit->unit->name,
                'credit_points' => $curriculumUnit->unit->credit_points,
                'year_level' => $curriculumUnit->year_level ?? 0,
                'semester_number' => null,
                'curriculum_version_id' => $curriculumUnit->curriculum_version_id,
                'curriculum_start_semester' => $curriculumUnit->curriculumVersion->effectiveFromSemester->name ?? 'Unknown',
                'elapsed_semesters' => null,
                'current_semester_in_curriculum' => null,
                'source' => 'common_curriculum',
            ]);
        }

        // Remove duplicates by unit_id (same unit can exist in multiple curriculum versions)
        // Priority: common_curriculum > new_curriculum > continuing_curriculum
        $unitPriority = [
            'common_curriculum' => 1,
            'new_curriculum' => 2,
            'continuing_curriculum' => 3,
        ];

        $curriculumUnits = $availableUnits
            ->groupBy('unit_id')
            ->map(function ($unitGroup) use ($unitPriority) {
                // Sort by priority and return the highest priority unit
                return $unitGroup->sortBy(function ($unit) use ($unitPriority) {
                    return $unitPriority[$unit['source']] ?? 999;
                })->first();
            })
            ->sortBy('code')
            ->values();

        // Get available lectures
        $lectures = Lecture::active()
            ->availableForAssignment()
            ->orderByName()
            ->get(['id', 'first_name', 'last_name', 'email', 'academic_rank']);

        // Get available syllabus templates
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
            'units' => $curriculumUnits,
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
        $courseOffering->load([
            'semester',
            'curriculumUnit.unit',
            'syllabusTemplate.unit:id,code,name',
            'syllabusTemplate.applicableCampus:id,name',
            'syllabusTemplate.applicableProgram:id,name',
            'lecture',
            'courseRegistrations' => function ($query) {
                $query->with('student')
                    ->orderBy('registration_date', 'desc');
            },
            'classSessions' => function ($query) {
                $query->with('room:id,name')->orderBy('session_date')
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
    public function edit(CourseOffering $courseOffering): Response
    {
        // Load the course offering with its related semester and curriculum unit data
        $courseOffering->load([
            'semester:id,name,code,start_date,end_date',
            'curriculumUnit:id,unit_id,year_level,semester_number',
            'curriculumUnit.unit:id,code,name,credit_points',
            'syllabusTemplate:id,title,version,description',
        ]);

        // Get available lectures
        $lectures = Lecture::active()
            ->availableForAssignment()
            ->orderByName()
            ->get(['id', 'first_name', 'last_name', 'email', 'academic_rank']);

        // Get available syllabus templates
        $syllabusTemplates = \App\Models\SyllabusTemplate::where('is_active', true)
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
        // Check if there are any registrations
        if ($courseOffering->courseRegistrations()->count() > 0) {
            return Redirect::back()
                ->with('error', 'Cannot delete course offering with existing registrations.');
        }

        $courseOffering->delete();

        return Redirect::route(CourseOfferingRoutes::INDEX)
            ->with('success', 'Course offering deleted successfully.');
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

        $courseOfferings = CourseOffering::whereIn('id', $request->ids)->get();

        foreach ($courseOfferings as $courseOffering) {
            if ($courseOffering->courseRegistrations()->count() > 0) {
                return Redirect::back()
                    ->with('error', 'Cannot delete course offerings with existing registrations.');
            }
        }

        CourseOffering::whereIn('id', $request->ids)->delete();

        return Redirect::route(CourseOfferingRoutes::INDEX)
            ->with('success', 'Selected course offerings deleted successfully.');
    }

    /**
     * Toggle course offering status
     */
    public function toggleStatus(CourseOffering $courseOffering): RedirectResponse
    {
        $newStatus = $courseOffering->enrollment_status === 'open' ? 'closed' : 'open';
        $courseOffering->update(['enrollment_status' => $newStatus]);

        return Redirect::back()
            ->with('success', "Course offering status updated to {$newStatus}.");
    }

    /**
     * Change the room for all existing class sessions of this course offering.
     * Validates that the selected room is available on the configured schedule days
     * and within the time range (schedule_time_start to schedule_time_end).
     */
    public function changeRoom(Request $request, CourseOffering $courseOffering): RedirectResponse
    {
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
     * Show the split course offering form
     */
    public function showSplit(CourseOffering $courseOffering): Response|RedirectResponse
    {
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
            'curriculumUnit.unit',
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
                    'curriculum_unit_id' => $courseOffering->curriculum_unit_id,
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
        $offeringsWithoutInstructors = CourseOffering::with(['curriculumUnit.unit', 'semester'])
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
                $courseOffering = CourseOffering::find($assignment['course_offering_id']);
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
     * Bulk update registration status for students
     */
    public function bulkUpdateRegistrationStatus(Request $request, CourseOffering $courseOffering)
    {
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
}
