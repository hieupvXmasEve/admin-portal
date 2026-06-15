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

        if ($feeCategory === 'egc' && ! $request->user()?->can('generate_egc_finance_charges')) {
            throw new AuthorizationException('Bạn không có quyền sinh phí EGC.');
        }

        $campusId = $request->user()?->can('view_finance_all_campus') ? null : (int) session('current_campus_id');

        $result = $assembler->handle(
            $feeCategory,
            (int) $request->input('semester_id'),
            (array) $request->input('scope', []),
            $campusId,
        );

        $token = $this->tokens->issue(
            (int) $request->user()->id,
            BatchJobType::ChargeGeneration,
            [
                'fee_category' => $feeCategory,
                'semester_id' => (int) $request->input('semester_id'),
                'scope' => (array) $request->input('scope', []),
            ],
            $result['lines'],
        );

        return ApiResponse::success([
            'preview_token' => $token,
            'lines' => array_map(fn ($line) => $line->toClientArray(), $result['lines']),
            'summary' => $result['summary'],
        ]);
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