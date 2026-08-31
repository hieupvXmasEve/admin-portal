<?php

declare(strict_types=1);

use App\Models\ExamResitAttempt;
use App\Modules\Academic\Delivery\Support\ResitFeeGate;
use App\Modules\Academic\Support\AcademicObligationSettlement;
use App\Shared\Contracts\Academic\AcademicFinanceSourceKeys;
use App\Shared\Contracts\Finance\DTO\ObligationSettlementResult;
use App\Shared\Contracts\Finance\ObligationSettlementReader;
use Illuminate\Validation\ValidationException;

function resitFeeGateSettlementResult(string $state): ObligationSettlementResult
{
    return new ObligationSettlementResult(
        source_system: AcademicFinanceSourceKeys::SOURCE_SYSTEM,
        source_kind: AcademicFinanceSourceKeys::EXAM_RESIT_ATTEMPT,
        source_ref: 'exam-resit:1',
        obligation_type: AcademicFinanceSourceKeys::EXAM_RESIT_FEE,
        finance_obligation_id: 1,
        settlement_state: $state,
        payable: 750000.0,
        paid: $state === ObligationSettlementResult::STATE_PAID ? 750000.0 : 0.0,
        discount: 0.0,
        outstanding: $state === ObligationSettlementResult::STATE_PAID ? 0.0 : 750000.0,
    );
}

function resitFeeGateWithState(string $state): ResitFeeGate
{
    $reader = Mockery::mock(ObligationSettlementReader::class);
    $reader->shouldReceive('getSettlement')
        ->once()
        ->andReturn(resitFeeGateSettlementResult($state));

    return new ResitFeeGate(new AcademicObligationSettlement($reader));
}

it('allows a live-settled resit without an unpaid reason', function () {
    $attempt = new ExamResitAttempt;
    $attempt->allow_unpaid_sitting_snapshot = false;

    expect(resitFeeGateWithState(ObligationSettlementResult::STATE_PAID)->assertCanProceed(
        $attempt,
        [],
        'blocked',
        'need reason',
    ))->toBe([]);
});

it('blocks an unsettled resit when unpaid sitting is not allowed', function () {
    $attempt = new ExamResitAttempt;
    $attempt->allow_unpaid_sitting_snapshot = false;

    expect(fn () => resitFeeGateWithState(ObligationSettlementResult::STATE_UNPAID)->assertCanProceed(
        $attempt,
        [],
        'Lệ phí thi lại chưa thanh toán, không thể xếp lịch khi chính sách không cho phép.',
        'need reason',
    ))->toThrow(ValidationException::class, 'Lệ phí thi lại chưa thanh toán, không thể xếp lịch khi chính sách không cho phép.');
});

it('records staff reason when unpaid sitting is allowed', function () {
    $attempt = new ExamResitAttempt;
    $attempt->allow_unpaid_sitting_snapshot = true;

    $extra = resitFeeGateWithState(ObligationSettlementResult::STATE_UNPAID)->assertCanProceed(
        $attempt,
        ['unpaid_sitting_reason' => 'Dean approved'],
        'blocked',
        'need reason',
    );

    expect($extra['unpaid_allowed_reason'])->toBe('Dean approved')
        ->and($extra)->toHaveKeys(['unpaid_allowed_reason', 'unpaid_allowed_by_user_id', 'unpaid_allowed_at']);
});
