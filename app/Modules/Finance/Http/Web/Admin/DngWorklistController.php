<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\Semester;
use App\Modules\Finance\Actions\CreateBatchDngFromChargesAction;
use App\Modules\Finance\Dng\Support\DngFeeTypeOptions;
use App\Modules\Finance\Http\Requests\Dng\StoreBatchDngFromChargesRequest;
use App\Modules\Finance\Queries\Dng\ListDngWorklistQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Unified DNG Worklist controller.
 *
 * Replaces:
 *  - BatchDngPageController (batch DNG from settlement data)
 *  - RetakeCourseChargeController::index (retake payment_pending list)
 *  - The "Tạo DNG" dialog in Settlement.vue
 *
 * All DNG creation now routes through here, ensuring charge↔DNG linking via
 * the dng_payment_request_charges pivot for correct webhook allocation.
 */
class DngWorklistController extends Controller
{
    public function __construct(
        private readonly ListDngWorklistQuery $worklistQuery,
        private readonly CreateBatchDngFromChargesAction $createBatchAction,
    ) {}

    /**
     * Render the unified DNG worklist page.
     * Fee type is driven by dng_fee_type query param; defaults to HP.
     */
    public function index(Request $request): Response
    {
        // Default to HP if not provided
        if (! $request->has('dng_fee_type')) {
            $request->merge(['dng_fee_type' => 'HP']);
        }

        $data = $this->worklistQuery->handle($request);

        $campusId = app()->bound('campus') ? app('campus')->id : null;

        $campuses = Campus::query()
            ->when($campusId, fn ($q) => $q->where('id', $campusId))
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Campus $c) => ['id' => $c->id, 'name' => $c->name, 'code' => $c->code])
            ->all();

        $semesters = Semester::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'code'])
            ->map(fn (Semester $s) => ['id' => $s->id, 'name' => $s->name, 'code' => $s->code])
            ->all();

        // Only expose fee types used in the worklist (exclude BHYT, PRE, etc.)
        $feeTypeOptions = collect(DngFeeTypeOptions::all())
            // ->filter(fn ($ft) => in_array($ft['value'], ['HP', 'HL', 'PTL', 'KHAC'], true))
            ->values()
            ->all();

        return Inertia::render('Finance/Operations/DngWorklist', [
            'students' => $data['students'],
            'filters' => $data['filters'],
            'summary' => $data['summary'],
            'feeTypeOptions' => $feeTypeOptions,
            'semesters' => $semesters,
            'campuses' => Inertia::once(fn () => $campuses),
        ]);
    }

    /**
     * Create batch DNG payment requests from charges for selected students.
     */
    public function store(StoreBatchDngFromChargesRequest $request): RedirectResponse
    {
        $result = $this->createBatchAction->handle($request->validated());

        $msg = "Đã tạo {$result['created']} DNG thành công.";

        if ($result['cancelled_old'] > 0) {
            $msg .= " Đã hủy {$result['cancelled_old']} DNG cũ.";
        }

        if ($result['failed'] > 0) {
            $msg .= " {$result['failed']} thất bại.";

            if (! empty($result['errors'])) {
                $msg .= ' Lỗi: '.implode('; ', array_slice($result['errors'], 0, 3));
            }
        }

        Inertia::flash($result['failed'] > 0 ? 'warning' : 'success', $msg);

        return back();
    }
}
