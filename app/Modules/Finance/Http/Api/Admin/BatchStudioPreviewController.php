<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Finance\Http\Requests\Batch\PreviewBatchChargesRequest;
use App\Modules\Finance\Http\Requests\Batch\PreviewBatchDngRequest;
use App\Modules\Finance\Http\Requests\Batch\PreviewBatchRemindersRequest;
use App\Modules\Finance\Queries\Batch\AssembleBatchChargePreviewQuery;
use App\Modules\Finance\Queries\Batch\AssembleBatchDngPreviewQuery;
use App\Modules\Finance\Queries\Batch\AssembleBatchReminderPreviewQuery;
use App\Modules\Finance\Services\Batch\BatchPreviewTokenService;
use App\Modules\Finance\Support\Batch\BatchJobType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class BatchStudioPreviewController extends Controller
{
    public function __construct(
        private readonly BatchPreviewTokenService $tokens,
    ) {}

    public function previewCharges(
        PreviewBatchChargesRequest $request,
        AssembleBatchChargePreviewQuery $assembler,
    ): JsonResponse {
        $feeCategory = (string) $request->input('fee_category');
        $semesterId = (int) $request->input('semester_id');

        $this->authorizeChargeCategory($request, $feeCategory);

        $campusId = $request->user()?->can('view_finance_all_campus') ? null : (int) session('current_campus_id');
        $scope = $assembler->normalizeScope($feeCategory, $semesterId, (array) $request->input('scope', []));

        $result = $assembler->handle(
            $feeCategory,
            $semesterId,
            $scope,
            $campusId,
        );

        $token = $this->tokens->issue(
            (int) $request->user()->id,
            BatchJobType::ChargeGeneration,
            [
                'fee_category' => $feeCategory,
                'semester_id' => $semesterId,
                'scope' => $scope,
            ],
            $result['lines'],
        );

        return ApiResponse::success([
            'preview_token' => $token,
            'lines' => array_map(fn ($line) => $line->toClientArray(), $result['lines']),
            'summary' => $result['summary'],
        ]);
    }

    private function authorizeChargeCategory(PreviewBatchChargesRequest $request, string $feeCategory): void
    {
        $allowed = match ($feeCategory) {
            'egc' => (bool) $request->user()?->can('generate_egc_finance_charges'),
            'major', 'non_academic' => (bool) $request->user()?->can('create_finance_charges'),
            default => false,
        };

        if (! $allowed) {
            throw new AuthorizationException(match ($feeCategory) {
                'egc' => 'Bạn không có quyền sinh phí EGC.',
                default => 'Bạn không có quyền sinh phí HP/Tuition.',
            });
        }
    }

    public function previewDng(
        PreviewBatchDngRequest $request,
        AssembleBatchDngPreviewQuery $assembler,
    ): JsonResponse {
        $campusId = $request->user()?->can('view_finance_all_campus') ? null : (int) session('current_campus_id');

        $result = $assembler->handle(
            (int) $request->input('semester_id'),
            (string) $request->input('dng_fee_type'),
            (array) $request->input('student_ids', []),
            $campusId,
        );

        $token = $this->tokens->issue(
            (int) $request->user()->id,
            BatchJobType::DngPush,
            [
                'dng_fee_type' => (string) $request->input('dng_fee_type'),
                'semester_id' => (int) $request->input('semester_id'),
            ],
            $result['lines'],
        );

        return ApiResponse::success([
            'preview_token' => $token,
            'lines' => array_map(fn ($line) => $line->toClientArray(), $result['lines']),
            'summary' => $result['summary'],
        ]);
    }

    public function previewReminders(
        PreviewBatchRemindersRequest $request,
        AssembleBatchReminderPreviewQuery $assembler,
    ): JsonResponse {
        $campusId = $request->user()?->can('view_finance_all_campus') ? null : (int) session('current_campus_id');

        $result = $assembler->handle(
            (string) $request->input('recipient'),
            $request->filled('semester_id') ? (int) $request->input('semester_id') : null,
            $campusId,
        );

        $token = $this->tokens->issue(
            (int) $request->user()->id,
            BatchJobType::Reminder,
            [
                'recipient' => (string) $request->input('recipient'),
                'semester_id' => $request->filled('semester_id') ? (int) $request->input('semester_id') : null,
            ],
            $result['lines'],
        );

        return ApiResponse::success([
            'preview_token' => $token,
            'lines' => array_map(fn ($line) => $line->toClientArray(), $result['lines']),
            'summary' => $result['summary'],
        ]);
    }
}
