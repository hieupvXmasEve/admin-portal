<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Dng\Support\DngFeeTypeOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

/**
 * Compatibility controller for the retired standalone DNG worklist.
 * Batch Studio is now the canonical bulk DNG payment-request creation surface.
 */
class DngWorklistController extends Controller
{
    /**
     * Redirect legacy bookmarks to the Batch Studio DNG wizard.
     */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('finance.batch-studio.dng', $this->safePrefill($request));
    }

    /**
     * Retired direct-write endpoint. DNG payment requests must be created
     * through Batch Studio so the preview-token recompute check cannot be bypassed.
     */
    public function store(Request $request): HttpResponse
    {
        return response('DNG payment requests must be created through Batch Studio.', 410);
    }

    /**
     * @return array<string, mixed>
     */
    private function safePrefill(Request $request): array
    {
        $prefill = [];
        $feeType = (string) $request->query('dng_fee_type', '');
        if (in_array($feeType, DngFeeTypeOptions::values(), true)) {
            $prefill['dng_fee_type'] = $feeType;
        }

        $semesterId = $request->query('semester_id');
        if (is_numeric($semesterId) && (int) $semesterId > 0) {
            $prefill['semester_id'] = (int) $semesterId;
        }

        $campusId = $request->query('campus_id');
        if ($request->user()?->can('view_finance_all_campus') && is_numeric($campusId) && (int) $campusId > 0) {
            $prefill['campus_id'] = (int) $campusId;
        }

        $studentIds = $this->studentIds($request->query('student_ids', []));
        if ($studentIds !== []) {
            $prefill['student_ids'] = $studentIds;
        }

        return $prefill;
    }

    /**
     * @return int[]
     */
    private function studentIds(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,\s]+/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->take(100)
            ->values()
            ->all();
    }
}
