<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\CourseRetakeRegistration;
use App\Models\Semester;
use App\Modules\Academic\Actions\CancelRetakeCourseRegistrationAction;
use App\Modules\Academic\Actions\CreateRetakeCourseRegistrationAction;
use App\Modules\Academic\Http\Requests\RetakeCourse\CancelRetakeCourseRequest;
use App\Modules\Academic\Http\Requests\RetakeCourse\ListRetakeCourseRequest;
use App\Modules\Academic\Http\Requests\RetakeCourse\StoreRetakeCourseRequest;
use App\Modules\Academic\Queries\ListRetakeCourseEligibleStudentsQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RetakeCourseRegistrationController extends Controller
{
    public function __construct(
        private readonly ListRetakeCourseEligibleStudentsQuery $eligibilityQuery,
    ) {}

    /**
     * List retake course registrations with filters.
     */
    public function index(ListRetakeCourseRequest $request): Response
    {
        $validated = $request->validated();

        $query = CourseRetakeRegistration::query()
            ->with(['student', 'unit', 'courseOffering.semester', 'semester', 'campus', 'approvedBy'])
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->whereHas('student', fn ($sq) => $sq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%"))
                        ->orWhereHas('unit', fn ($sq) => $sq->where('code', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($validated['semester_id'] ?? null, fn ($q, $id) => $q->where('semester_id', $id))
            ->when($validated['campus_id'] ?? null, fn ($q, $id) => $q->where('campus_id', $id))
            ->when($validated['unit_id'] ?? null, fn ($q, $id) => $q->where('unit_id', $id))
            ->orderBy($validated['sort'] ?? 'created_at', $validated['direction'] ?? 'desc')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return Inertia::render('Academic/RetakeCourse/Index', [
            'registrations' => $query,
            'filters' => $request->only(['search', 'status', 'semester_id', 'campus_id', 'unit_id', 'sort', 'direction', 'per_page']),
            'semesters' => Semester::orderByDesc('start_date')->get(['id', 'name', 'code']),
            'campuses' => Campus::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Show create form with eligible students data.
     */
    public function create(ListRetakeCourseRequest $request): Response
    {
        $validated = $request->validated();

        $eligibleStudents = $this->eligibilityQuery->handle([
            'campus_id' => $validated['campus_id'] ?? session('current_campus_id'),
            'semester_id' => $validated['semester_id'] ?? null,
            'search' => $validated['search'] ?? null,
            'unit_id' => $validated['unit_id'] ?? null,
        ]);

        $totalEligibleStudents = $eligibleStudents->unique('student.id')->count();

        return Inertia::render('Academic/RetakeCourse/Create', [
            'eligible_students' => $eligibleStudents,
            'total_eligible_students' => $totalEligibleStudents,
            'filters' => $request->only(['search', 'semester_id', 'campus_id', 'unit_id']),
            'semesters' => Semester::orderByDesc('start_date')->get(['id', 'name', 'code']),
            'campuses' => Campus::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Store a new retake course registration.
     */
    public function store(StoreRetakeCourseRequest $request): RedirectResponse
    {
        CreateRetakeCourseRegistrationAction::run($request->validated());

        Inertia::flash('success', 'Đăng ký học lại thành công.');

        return redirect()->route('academic.retake-course.index');
    }

    /**
     * Cancel a retake course registration.
     */
    public function cancel(CancelRetakeCourseRequest $request, CourseRetakeRegistration $registration): RedirectResponse
    {
        CancelRetakeCourseRegistrationAction::run([
            'registration_id' => $registration->id,
            'reason' => $request->validated('reason'),
        ]);

        Inertia::flash('success', 'Đã hủy đăng ký học lại.');

        return back();
    }
}
