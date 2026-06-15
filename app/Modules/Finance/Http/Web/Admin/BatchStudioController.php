<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Finance\Actions\CreateBatchDngFromChargesAction;
use App\Modules\Finance\Actions\Egc\GenerateEgcChargesAction;
use App\Modules\Finance\Actions\Major\GenerateMajorChargesAction;
use App\Modules\Finance\Actions\Operations\GenerateBatchChargesAction;
use App\Modules\Finance\Actions\Operations\SendDueItemParentRemindersAction;
use App\Modules\Finance\Actions\Operations\SendDueItemRemindersAction;
use App\Modules\Finance\Dng\Support\DngFeeTypeOptions;
use App\Modules\Finance\Enums\NonAcademicChargeTypeEnum;
use App\Modules\Finance\Http\Requests\Batch\CommitBatchChargesRequest;
use App\Modules\Finance\Http\Requests\Batch\CommitBatchDngRequest;
use App\Modules\Finance\Http\Requests\Batch\CommitBatchRemindersRequest;
use App\Modules\Finance\Queries\Batch\AssembleBatchChargePreviewQuery;
use App\Modules\Finance\Queries\Batch\AssembleBatchDngPreviewQuery;
use App\Modules\Finance\Queries\Batch\AssembleBatchReminderPreviewQuery;
use App\Modules\Finance\Services\Batch\BatchPreviewTokenService;
use App\Modules\Finance\Support\Batch\BatchJobType;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BatchStudioController extends Controller
{
    public function hub(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Finance/BatchStudio/Hub', [
            'jobs' => [
                'charge_generation' => (bool) ($user?->can('create_finance_charges')
                    || $user?->can('generate_egc_finance_charges')),
                'dng_push' => (bool) $user?->can('create_finance_payments'),
                'reminder' => (bool) $user?->can('view_finance_operations_due_calendar'),
            ],
        ]);
    }

    public function charges(Request $request): Response
    {
        return Inertia::render('Finance/BatchStudio/ChargeGeneration', [
            'feeTypeOptions' => NonAcademicChargeTypeEnum::forSelect(),
        ]);
    }

    public function dng(Request $request): Response
    {
        return Inertia::render('Finance/BatchStudio/DngPush', [
            'dngFeeTypeOptions' => DngFeeTypeOptions::all(),
        ]);
    }

    public function reminders(Request $request): Response
    {
        return Inertia::render('Finance/BatchStudio/Reminders');
    }

    public function commitCharges(
        CommitBatchChargesRequest $request,
        BatchPreviewTokenService $tokens,
        AssembleBatchChargePreviewQuery $assembler,
    ): RedirectResponse {
        $userId = (int) $request->user()->id;
        $token = (string) $request->input('preview_token');
        $selectedKeys = (array) $request->input('selected_keys');

        $scope = $tokens->scope($userId, $token, BatchJobType::ChargeGeneration);
        if ($scope === null) {
            throw ValidationException::withMessages([
                'preview_token' => 'Phiên xem trước đã hết hạn hoặc đã dùng. Vui lòng xem trước lại.',
            ]);
        }

        $feeCategory = (string) ($scope['fee_category'] ?? '');
        $semesterId = (int) ($scope['semester_id'] ?? 0);

        if ($feeCategory === 'egc' && ! $request->user()?->can('generate_egc_finance_charges')) {
            throw new AuthorizationException('Bạn không có quyền sinh phí EGC.');
        }

        $campusId = $request->user()?->can('view_finance_all_campus') ? null : (int) session('current_campus_id');

        $currentByKey = $this->recomputeOrFail(
            $userId, $token, BatchJobType::ChargeGeneration, $selectedKeys, $tokens,
            fn () => collect($assembler->handle($feeCategory, $semesterId, (array) ($scope['scope'] ?? []), $campusId)['lines'])
                ->keyBy(fn (BatchPreviewLine $line) => $line->key)->all(),
        );

        $studentIds = collect($selectedKeys)
            ->map(fn (string $key) => (int) (explode(':', $key)[3] ?? 0))
            ->filter()->values()->all();

        $dueDate = (string) ($request->input('due_date') ?? now()->addDays(30)->toDateString());

        $summary = $this->runGeneration(
            $feeCategory,
            $semesterId,
            $studentIds,
            $dueDate,
            $currentByKey,
            (array) ($scope['scope'] ?? []),
        );

        return Inertia::flash('batch_result', [
            'job' => 'charge_generation',
            'fee_category' => $feeCategory,
            'summary' => $summary,
        ])->back();
    }

    public function commitDng(
        CommitBatchDngRequest $request,
        BatchPreviewTokenService $tokens,
        CreateBatchDngFromChargesAction $action,
        AssembleBatchDngPreviewQuery $assembler,
    ): RedirectResponse {
        $userId = (int) $request->user()->id;
        $token = (string) $request->input('preview_token');
        $selectedKeys = (array) $request->input('selected_keys');

        $scope = $tokens->scope($userId, $token, BatchJobType::DngPush);
        if ($scope === null) {
            throw ValidationException::withMessages([
                'preview_token' => 'Phiên xem trước đã hết hạn hoặc đã dùng. Vui lòng xem trước lại.',
            ]);
        }

        $dngFeeType = (string) ($scope['dng_fee_type'] ?? '');
        $semesterId = (int) ($scope['semester_id'] ?? 0);
        $campusId = $request->user()?->can('view_finance_all_campus') ? null : (int) session('current_campus_id');

        $studentIds = collect($selectedKeys)
            ->map(fn (string $key) => (int) (explode(':', $key)[2] ?? 0))
            ->filter()->values()->all();

        $this->recomputeOrFail(
            $userId, $token, BatchJobType::DngPush, $selectedKeys, $tokens,
            fn () => collect($assembler->handle($semesterId, $dngFeeType, $studentIds, $campusId)['lines'])
                ->keyBy(fn (BatchPreviewLine $line) => $line->key)->all(),
        );

        $result = $action->handle([
            'student_ids' => $studentIds,
            'dng_fee_type' => $dngFeeType,
            'due_date' => (string) $request->input('due_date'),
            'semester_id' => $semesterId,
            'description' => (string) $request->input('description'),
            'estimate_time' => (string) $request->input('estimate_time'),
            'amount_overrides' => $request->input('amount_overrides'),
        ]);

        return Inertia::flash('batch_result', [
            'job' => 'dng_push',
            'summary' => $result,
        ])->back();
    }

    public function commitReminders(
        CommitBatchRemindersRequest $request,
        BatchPreviewTokenService $tokens,
        AssembleBatchReminderPreviewQuery $assembler,
    ): RedirectResponse {
        $userId = (int) $request->user()->id;
        $token = (string) $request->input('preview_token');
        $selectedKeys = (array) $request->input('selected_keys');

        $scope = $tokens->scope($userId, $token, BatchJobType::Reminder);
        if ($scope === null) {
            throw ValidationException::withMessages([
                'preview_token' => 'Phiên xem trước đã hết hạn hoặc đã dùng. Vui lòng xem trước lại.',
            ]);
        }

        $recipient = (string) ($scope['recipient'] ?? 'student');
        $semesterId = isset($scope['semester_id']) ? (int) $scope['semester_id'] : null;
        $campusId = $request->user()?->can('view_finance_all_campus') ? null : (int) session('current_campus_id');

        $this->recomputeOrFail(
            $userId, $token, BatchJobType::Reminder, $selectedKeys, $tokens,
            fn () => collect($assembler->handle($recipient, $semesterId, $campusId)['lines'])
                ->keyBy(fn (BatchPreviewLine $line) => $line->key)->all(),
        );

        $itemIds = collect($selectedKeys)
            ->map(fn (string $key) => str_replace('reminder:', '', $key))
            ->values()->all();

        $result = $recipient === 'parent'
            ? SendDueItemParentRemindersAction::run(['item_ids' => $itemIds])
            : SendDueItemRemindersAction::run(['item_ids' => $itemIds]);

        return Inertia::flash('batch_result', ['job' => 'reminder', 'summary' => $result])->back();
    }

    /**
     * Re-resolve the selected line keys from current DB state via $reresolve, then
     * recompute-compare against the issued token (one-time consume). Throws on any drift.
     *
     * @param  string[]  $selectedKeys
     * @param  callable(): array<string, BatchPreviewLine>  $reresolve  key => current line
     * @return array<string, BatchPreviewLine>
     */
    private function recomputeOrFail(
        int $userId,
        string $token,
        BatchJobType $jobType,
        array $selectedKeys,
        BatchPreviewTokenService $tokens,
        callable $reresolve,
    ): array {
        $currentByKey = $reresolve();

        $subset = [];
        foreach ($selectedKeys as $key) {
            $line = $currentByKey[$key] ?? null;
            $subset[$key] = $line?->hashPayload ?? ['__gone__' => $key];
        }

        $verdict = $tokens->consume($userId, $token, $jobType, $subset);

        if (! $verdict['ok']) {
            $message = $verdict['missing']
                ? 'Phiên xem trước đã hết hạn hoặc đã dùng. Vui lòng xem trước lại.'
                : count($verdict['changed']).' dòng đã thay đổi từ lúc xem trước. Vui lòng xem trước lại.';
            throw ValidationException::withMessages(['preview_token' => $message]);
        }

        return $currentByKey;
    }

    /**
     * @param  int[]  $studentIds
     * @param  array<string, BatchPreviewLine>  $currentByKey
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    private function runGeneration(
        string $feeCategory,
        int $semesterId,
        array $studentIds,
        string $dueDate,
        array $currentByKey,
        array $scope,
    ): array {
        return match ($feeCategory) {
            'major' => GenerateMajorChargesAction::run([
                'semester_id' => $semesterId,
                'due_date' => $dueDate,
                'student_ids' => $studentIds,
            ]),
            'egc' => GenerateEgcChargesAction::run([
                'semester_id' => $semesterId,
                'due_date' => $dueDate,
                'students' => collect($studentIds)->map(function (int $id) use ($currentByKey, $feeCategory, $semesterId) {
                    $key = sprintf('charge:%s:student:%d:semester:%d', $feeCategory, $id, $semesterId);
                    $line = $currentByKey[$key] ?? null;

                    return [
                        'student_id' => $id,
                        'block_count' => (int) ($line?->display['block_count'] ?? 2),
                    ];
                })->all(),
            ]),
            'non_academic' => GenerateBatchChargesAction::run(array_merge($scope, [
                'semester_id' => $semesterId,
                'due_date' => $dueDate,
                'student_ids' => $studentIds,
            ])),
            default => [],
        };
    }
}