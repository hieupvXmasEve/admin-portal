<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\CourseRetakeRegistration;
use App\Modules\Academic\Delivery\Actions\CancelRetakeCourseRegistrationAction;
use App\Modules\Academic\Delivery\Actions\CreateRetakeCourseRegistrationAction;
use App\Modules\Academic\Delivery\Actions\SyncPaidRetakeRegistrationsAction;
use App\Modules\Academic\Delivery\Http\Requests\RetakeCourse\CancelRetakeCourseRequest;
use App\Modules\Academic\Delivery\Http\Requests\RetakeCourse\ListRetakeCourseRequest;
use App\Modules\Academic\Delivery\Http\Requests\RetakeCourse\StoreRetakeCourseRequest;
use App\Modules\Academic\Catalog\Queries\GetSemesterReferenceOptionsQuery;
use App\Modules\Academic\Delivery\Queries\ListRetakeCourseEligibleStudentsQuery;
use App\Modules\Academic\Delivery\Queries\ListRetakeCourseRegistrationsQuery;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\Institution\DTO\CampusReference;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RetakeCourseRegistrationController extends Controller
{
    public function __construct(
        private readonly ListRetakeCourseEligibleStudentsQuery $eligibilityQuery,
        private readonly ListRetakeCourseRegistrationsQuery $registrationsQuery,
        private readonly GetSemesterReferenceOptionsQuery $semesters,
        private readonly CampusReferenceReader $campuses,
    ) {}

    /**
     * List retake course registrations with filters.
     */
    public function index(ListRetakeCourseRequest $request): Response
    {
        $validated = $request->validated();
        $filters = array_replace([
            'search' => '',
            'status' => null,
            'operation_state' => null,
            'semester_id' => null,
            'unit_id' => null,
            'sort' => null,
            'direction' => null,
            'per_page' => 15,
        ], $validated);

        $filters['semester_id'] = $filters['semester_id'] !== null ? (int) $filters['semester_id'] : null;
        $filters['unit_id'] = $filters['unit_id'] !== null ? (int) $filters['unit_id'] : null;
        $filters['per_page'] = (int) $filters['per_page'];

        $result = $this->registrationsQuery->handle($filters, session('current_campus_id'));

        return Inertia::render('Academic/RetakeCourse/Index', [
            'registrations' => $result['registrations'],
            'summary' => $result['summary'],
            'filters' => $filters,
            'semesters' => $this->semesters->options(),
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
            'failed_semester_id' => $validated['failed_semester_id'] ?? null,
            'search' => $validated['search'] ?? null,
            'unit_id' => $validated['unit_id'] ?? null,
        ]);

        $totalEligibleStudents = $eligibleStudents->unique('student.id')->count();

        return Inertia::render('Academic/RetakeCourse/Create', [
            'eligible_students' => $eligibleStudents,
            'total_eligible_students' => $totalEligibleStudents,
            'filters' => $request->only(['search', 'semester_id', 'failed_semester_id', 'campus_id', 'unit_id']),
            'semesters' => $this->semesters->options(),
            'campuses' => array_map(
                static fn (CampusReference $campus): array => $campus->toArray(),
                $this->campuses->all(),
            ),
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

    public function sync(CourseRetakeRegistration $registration, SyncPaidRetakeRegistrationsAction $action): RedirectResponse
    {
        $result = $action->runForRegistration($registration->id);

        if ($result['synced'] > 0) {
            Inertia::flash('success', 'Đã liên kết đăng ký học lại với lớp hiện có.');
        } elseif ($result['waiting_for_class'] > 0) {
            Inertia::flash('warning', 'Sinh viên đã thanh toán, đang chờ staff thêm vào lớp.');
        } else {
            Inertia::flash('info', 'Không có dữ liệu đủ điều kiện để đồng bộ.');
        }

        return back();
    }
}
