<?php

declare(strict_types=1);

use App\Modules\Finance\Enums\LifecycleDueExceptionResolutionAction;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewEventType;
use App\Modules\Finance\Support\LifecycleDueExceptionReviewEventMapper;

it('maps resolution actions to history event types', function () {
    expect(LifecycleDueExceptionReviewEventMapper::eventTypeForResolution(LifecycleDueExceptionResolutionAction::Acknowledge))
        ->toBe(LifecycleDueExceptionReviewEventType::Acknowledged);

    expect(LifecycleDueExceptionReviewEventMapper::eventTypeForResolution(LifecycleDueExceptionResolutionAction::KeepAsDebt))
        ->toBe(LifecycleDueExceptionReviewEventType::KeptAsDebt);

    expect(LifecycleDueExceptionReviewEventMapper::eventTypeForResolution(LifecycleDueExceptionResolutionAction::RouteToSettlement))
        ->toBe(LifecycleDueExceptionReviewEventType::RoutedToSettlement);

    expect(LifecycleDueExceptionReviewEventMapper::eventTypeForResolution(LifecycleDueExceptionResolutionAction::CancelDng))
        ->toBe(LifecycleDueExceptionReviewEventType::CancelRequested);
});
