<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

use App\Modules\Finance\Actions\CancelDngPaymentRequestAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Enums\LifecycleDueExceptionResolutionAction;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewEventType;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewStatus;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReview;
use App\Modules\Finance\Queries\Operations\GetLifecycleDueExceptionImpactPreviewQuery;
use App\Modules\Finance\Support\LifecycleDueExceptionReasonResolver;
use App\Modules\Finance\Support\LifecycleDueExceptionReviewEventMapper;
use App\Modules\Finance\Support\LifecycleDueExceptionRowMapper;
use App\Modules\Finance\Support\LifecycleDueItemPredicate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ResolveLifecycleDueExceptionAction
{
    public function __construct(
        private readonly CancelDngPaymentRequestAction $cancelDngPaymentRequestAction,
        private readonly GetLifecycleDueExceptionImpactPreviewQuery $impactPreviewQuery,
        private readonly RecordLifecycleDueExceptionReviewEventAction $recordEventAction,
    ) {}

    /**
     * @return array{review: FinanceLifecycleDueExceptionReview, impact_preview?: array<string, mixed>}
     */
    public function run(
        DngPaymentRequest $request,
        LifecycleDueExceptionResolutionAction $action,
        string $reason,
        int $userId,
        bool $canCancelDng,
        bool $canVoidCharges,
    ): array {
        if (! LifecycleDueItemPredicate::isOpenDueRequest($request)) {
            throw new \RuntimeException('DNG request is no longer an open due item.');
        }

        if (! LifecycleDueItemPredicate::isLifecycleException($request->student)) {
            throw new \RuntimeException('DNG request is not a lifecycle due exception.');
        }

        $exceptionReason = LifecycleDueExceptionReasonResolver::resolve($request->student);
        $impactPreview = $this->impactPreviewQuery->handle($request);

        return match ($action) {
            LifecycleDueExceptionResolutionAction::Acknowledge => $this->persistReview(
                $request,
                $exceptionReason,
                LifecycleDueExceptionReviewStatus::Acknowledged,
                $action,
                $reason,
                $userId,
            ),
            LifecycleDueExceptionResolutionAction::KeepAsDebt => $this->persistReview(
                $request,
                $exceptionReason,
                LifecycleDueExceptionReviewStatus::KeptAsDebt,
                $action,
                $reason,
                $userId,
            ),
            LifecycleDueExceptionResolutionAction::RouteToSettlement => $this->persistReview(
                $request,
                $exceptionReason,
                LifecycleDueExceptionReviewStatus::RoutedToSettlement,
                $action,
                $reason,
                $userId,
                ['impact_preview' => $impactPreview],
            ),
            LifecycleDueExceptionResolutionAction::CancelDng => $this->cancelDng(
                $request,
                $exceptionReason,
                $action,
                $reason,
                $userId,
                $canCancelDng,
                $impactPreview,
            ),
            LifecycleDueExceptionResolutionAction::CancelDngAndVoidLinkedCharge => $this->cancelDng(
                $request,
                $exceptionReason,
                $action,
                $reason,
                $userId,
                $canCancelDng && $canVoidCharges,
                $impactPreview,
                requireVoidPermission: true,
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $impactPreview
     * @return array{review: FinanceLifecycleDueExceptionReview, impact_preview: array<string, mixed>}
     */
    private function cancelDng(
        DngPaymentRequest $request,
        $exceptionReason,
        LifecycleDueExceptionResolutionAction $action,
        string $reason,
        int $userId,
        bool $authorized,
        array $impactPreview,
        bool $requireVoidPermission = false,
    ): array {
        if (! $authorized) {
            throw new AuthorizationException(
                $requireVoidPermission
                    ? 'Bạn không có quyền hủy DNG và void phí liên kết.'
                    : 'Bạn không có quyền hủy DNG request.'
            );
        }

        $blockingReasons = LifecycleDueExceptionRowMapper::blockingReasons($request);
        if ($blockingReasons !== []) {
            throw new \RuntimeException(implode(' ', $blockingReasons));
        }

        $existingReview = FinanceLifecycleDueExceptionReview::query()
            ->where('dng_payment_request_id', $request->id)
            ->first();
        $fromStatus = $existingReview?->status ?? LifecycleDueExceptionReviewStatus::Open;
        $dngStatusBefore = $request->status;

        try {
            $review = DB::transaction(function () use ($request, $exceptionReason, $action, $reason, $userId, $impactPreview, $fromStatus, $dngStatusBefore, $existingReview, $requireVoidPermission): FinanceLifecycleDueExceptionReview {
                // FIN-34: record the cancel/void *attempt* events INSIDE the same
                // transaction as the status mutation. Previously they were written
                // before the transaction opened, so if the DNG cancel API failed and
                // the transaction rolled back, a committed "CancelRequested"
                // transition survived that the review row never durably reached —
                // audit and review state diverged and the attempt read as a completed
                // transition. Recording them inside the transaction means they commit
                // or roll back atomically with the state change; on rollback the catch
                // block writes the paired CancelFailed/VoidFailed outcome instead.
                $this->recordEventAction->run(
                    request: $request,
                    eventType: LifecycleDueExceptionReviewEventType::CancelRequested,
                    fromStatus: $fromStatus,
                    toStatus: LifecycleDueExceptionReviewStatus::CancelRequested,
                    resolutionAction: $action,
                    resolutionReason: $reason,
                    performedByUserId: $userId,
                    review: $existingReview,
                    dngStatusBefore: $dngStatusBefore,
                    metadata: ['impact_preview' => $impactPreview],
                );

                if ($requireVoidPermission) {
                    $this->recordEventAction->run(
                        request: $request,
                        eventType: LifecycleDueExceptionReviewEventType::VoidRequested,
                        fromStatus: $fromStatus,
                        toStatus: LifecycleDueExceptionReviewStatus::CancelRequested,
                        resolutionAction: $action,
                        resolutionReason: $reason,
                        performedByUserId: $userId,
                        review: $existingReview,
                        dngStatusBefore: $dngStatusBefore,
                        metadata: ['impact_preview' => $impactPreview],
                    );
                }

                $review = FinanceLifecycleDueExceptionReview::query()->updateOrCreate(
                    ['dng_payment_request_id' => $request->id],
                    [
                        'student_id' => $request->student_id,
                        'exception_reason' => $exceptionReason,
                        'status' => LifecycleDueExceptionReviewStatus::CancelRequested,
                        'resolution_action' => $action,
                        'resolution_reason' => $reason,
                        'resolved_by_user_id' => $userId,
                        'last_seen_at' => now(),
                        'metadata' => [
                            'impact_preview' => $impactPreview,
                        ],
                    ]
                );

                $this->cancelDngPaymentRequestAction->run($request->fresh());

                $review->update([
                    'status' => LifecycleDueExceptionReviewStatus::Resolved,
                    'resolved_at' => now(),
                    'metadata' => array_merge($review->metadata ?? [], [
                        'impact_preview' => $impactPreview,
                        'after_dng_status' => $request->fresh()->status,
                    ]),
                ]);

                return $review->fresh();
            });

            $dngStatusAfter = $request->fresh()->status;

            $this->recordEventAction->run(
                request: $request,
                eventType: LifecycleDueExceptionReviewEventType::CancelSucceeded,
                fromStatus: LifecycleDueExceptionReviewStatus::CancelRequested,
                toStatus: LifecycleDueExceptionReviewStatus::Resolved,
                resolutionAction: $action,
                resolutionReason: $reason,
                performedByUserId: $userId,
                review: $review,
                dngStatusBefore: $dngStatusBefore,
                dngStatusAfter: $dngStatusAfter,
                metadata: ['impact_preview' => $impactPreview],
            );

            if ($requireVoidPermission) {
                $this->recordEventAction->run(
                    request: $request,
                    eventType: LifecycleDueExceptionReviewEventType::VoidSucceeded,
                    fromStatus: LifecycleDueExceptionReviewStatus::CancelRequested,
                    toStatus: LifecycleDueExceptionReviewStatus::Resolved,
                    resolutionAction: $action,
                    resolutionReason: $reason,
                    performedByUserId: $userId,
                    review: $review,
                    dngStatusBefore: $dngStatusBefore,
                    dngStatusAfter: $dngStatusAfter,
                    metadata: [
                        'impact_preview' => $impactPreview,
                        'linked_charges' => $impactPreview['linked_charges'] ?? [],
                    ],
                );
            }

            return [
                'review' => $review,
                'impact_preview' => $impactPreview,
            ];
        } catch (\Throwable $e) {
            $this->recordEventAction->run(
                request: $request,
                eventType: LifecycleDueExceptionReviewEventType::CancelFailed,
                fromStatus: LifecycleDueExceptionReviewStatus::CancelRequested,
                toStatus: $fromStatus,
                resolutionAction: $action,
                resolutionReason: $reason,
                performedByUserId: $userId,
                review: $existingReview,
                dngStatusBefore: $dngStatusBefore,
                dngStatusAfter: $request->fresh()->status,
                metadata: [
                    'impact_preview' => $impactPreview,
                    'failure_summary' => $e->getMessage(),
                ],
            );

            if ($requireVoidPermission) {
                $this->recordEventAction->run(
                    request: $request,
                    eventType: LifecycleDueExceptionReviewEventType::VoidFailed,
                    fromStatus: LifecycleDueExceptionReviewStatus::CancelRequested,
                    toStatus: $fromStatus,
                    resolutionAction: $action,
                    resolutionReason: $reason,
                    performedByUserId: $userId,
                    review: $existingReview,
                    dngStatusBefore: $dngStatusBefore,
                    dngStatusAfter: $request->fresh()->status,
                    metadata: [
                        'impact_preview' => $impactPreview,
                        'failure_summary' => $e->getMessage(),
                    ],
                );
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{review: FinanceLifecycleDueExceptionReview}
     */
    private function persistReview(
        DngPaymentRequest $request,
        $exceptionReason,
        LifecycleDueExceptionReviewStatus $status,
        LifecycleDueExceptionResolutionAction $action,
        string $reason,
        int $userId,
        array $metadata = [],
    ): array {
        $existingReview = FinanceLifecycleDueExceptionReview::query()
            ->where('dng_payment_request_id', $request->id)
            ->first();
        $fromStatus = $existingReview?->status ?? LifecycleDueExceptionReviewStatus::Open;
        $dngStatusBefore = $request->status;

        $review = DB::transaction(function () use ($request, $exceptionReason, $status, $action, $reason, $userId, $metadata): FinanceLifecycleDueExceptionReview {
            return FinanceLifecycleDueExceptionReview::query()->updateOrCreate(
                ['dng_payment_request_id' => $request->id],
                [
                    'student_id' => $request->student_id,
                    'exception_reason' => $exceptionReason,
                    'status' => $status,
                    'resolution_action' => $action,
                    'resolution_reason' => $reason,
                    'resolved_at' => now(),
                    'resolved_by_user_id' => $userId,
                    'last_seen_at' => now(),
                    'metadata' => $metadata === [] ? null : $metadata,
                ]
            );
        });

        $this->recordEventAction->run(
            request: $request,
            eventType: LifecycleDueExceptionReviewEventMapper::eventTypeForResolution($action),
            fromStatus: $fromStatus,
            toStatus: $status,
            resolutionAction: $action,
            resolutionReason: $reason,
            performedByUserId: $userId,
            review: $review,
            dngStatusBefore: $dngStatusBefore,
            dngStatusAfter: $request->fresh()->status,
            metadata: $metadata,
        );

        return ['review' => $review];
    }
}
