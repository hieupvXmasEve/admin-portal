<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\CourseRetakeRegistration;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Modules\Finance\Actions\CreateRetakeCourseChargeAction;
use App\Modules\Finance\Actions\CreateRetakeCourseChargeSimpleAction;
use App\Modules\Finance\Http\Requests\RetakeCourse\StoreRetakeCourseChargeRequest;
use App\Modules\Finance\Http\Requests\RetakeCourse\StoreRetakeCourseChargeSimpleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RetakeCourseChargeController extends Controller
{
    public function __construct(
        private readonly CreateRetakeCourseChargeAction $createChargeAction,
        private readonly CreateRetakeCourseChargeSimpleAction $createChargeSimpleAction,
    ) {}

    /**
     * List payment_pending retake registrations grouped by student.
     *
     * Each row represents one registration but the "Tạo DNG" action will
     * collect ALL payment_pending registrations for that student and create
     * a single aggregate DNG request covering every pending charge.
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
            ->where('status', CourseRetakeRegistration::STATUS_PAYMENT_PENDING)
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

        // Attach pending_units_count + pending_total_fee for each student on the current page
        $studentIds = $registrations->pluck('student_id')->unique()->all();

        $pendingByStudent = CourseRetakeRegistration::query()
            ->where('status', CourseRetakeRegistration::STATUS_PAYMENT_PENDING)
            ->whereIn('student_id', $studentIds)
            ->selectRaw('student_id, COUNT(*) as pending_count, SUM(retake_fee) as pending_total')
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $registrations->getCollection()->transform(function (CourseRetakeRegistration $reg) use ($pendingByStudent) {
            $summary = $pendingByStudent->get($reg->student_id);
            $reg->pending_units_count = (int) ($summary?->pending_count ?? 1);
            $reg->pending_total_fee = (float) ($summary?->pending_total ?? $reg->retake_fee);

            return $reg;
        });

        return Inertia::render('Finance/RetakeCourse/Index', [
            'registrations' => $registrations,
            'filters' => $request->only(['search', 'semester_id', 'campus_id', 'sort', 'direction', 'per_page']),
            'semesters' => Semester::orderByDesc('start_date')->get(['id', 'name', 'code']),
            'campuses' => Campus::orderBy('name')->get(['id', 'name', 'code']),
            'chargeTypeOptions' => Inertia::once(fn () => [
                ['value' => FinanceCharge::TYPE_RETAKE_FEE,     'label' => 'Phí học lại môn (HL)'],
                ['value' => FinanceCharge::TYPE_EXAM_RESIT_FEE, 'label' => 'Phí thi lại (PTL)'],
                ['value' => FinanceCharge::TYPE_MANUAL_FEE,     'label' => 'Phí thủ công (KHAC)'],
            ]),
        ]);
    }

    /**
     * Store a new retake course charge (with DNG payment request).
     */
    public function store(StoreRetakeCourseChargeRequest $request): RedirectResponse
    {
        $this->createChargeAction->handle($request->validated());

        Inertia::flash('success', 'Đã tạo phí học lại và gửi yêu cầu thanh toán DNG.');

        return back();
    }

    /**
     * Store a new retake course charge without creating a DNG payment request.
     */
    public function storeSimple(StoreRetakeCourseChargeSimpleRequest $request): RedirectResponse
    {
        $this->createChargeSimpleAction->handle($request->validated());

        Inertia::flash('success', 'Đã tạo khoản phí thành công.');

        return back();
    }
}
