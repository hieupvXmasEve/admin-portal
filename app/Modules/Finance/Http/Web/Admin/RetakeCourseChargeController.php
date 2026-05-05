<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\CourseRetakeRegistration;
use App\Models\Semester;
use App\Modules\Finance\Actions\CreateRetakeCourseChargeAction;
use App\Modules\Finance\Http\Requests\RetakeCourse\StoreRetakeCourseChargeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RetakeCourseChargeController extends Controller
{
    public function __construct(
        private readonly CreateRetakeCourseChargeAction $createChargeAction,
    ) {}

    /**
     * List approved retake registrations waiting for charge creation.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'sort' => ['nullable', 'string', 'in:created_at,retake_fee'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $registrations = CourseRetakeRegistration::query()
            ->where('status', CourseRetakeRegistration::STATUS_APPROVED)
            ->with(['student', 'unit', 'courseOffering.semester', 'semester', 'campus', 'approvedBy'])
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->whereHas('student', fn ($sq) => $sq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%"))
                        ->orWhereHas('unit', fn ($sq) => $sq->where('code', 'like', "%{$search}%"));
                });
            })
            ->when($validated['semester_id'] ?? null, fn ($q, $id) => $q->where('semester_id', $id))
            ->when($validated['campus_id'] ?? null, fn ($q, $id) => $q->where('campus_id', $id))
            ->orderBy($validated['sort'] ?? 'created_at', $validated['direction'] ?? 'desc')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return Inertia::render('Finance/RetakeCourse/Index', [
            'registrations' => $registrations,
            'filters' => $request->only(['search', 'semester_id', 'campus_id', 'sort', 'direction', 'per_page']),
            'semesters' => Semester::orderByDesc('start_date')->get(['id', 'name', 'code']),
            'campuses' => Campus::orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Store a new retake course charge.
     */
    public function store(StoreRetakeCourseChargeRequest $request): RedirectResponse
    {
        $this->createChargeAction->handle($request->validated());

        Inertia::flash('success', 'Đã tạo phí học lại và gửi yêu cầu thanh toán DNG.');

        return back();
    }
}
