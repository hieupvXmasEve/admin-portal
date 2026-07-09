<?php

declare(strict_types=1);

use App\Modules\Finance\Actions\SettleInstallmentFromDngAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Events\ChargeFullySettled;
use App\Modules\Finance\Events\InstallmentPushed;
use App\Modules\Finance\Events\InstallmentPushFailed;
use App\Modules\Finance\Jobs\PushNextInstallmentJob;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

// Reuses fixtures + helpers from PushNextInstallmentActionTest.php.
// Pest auto-loads files in the same directory.

beforeEach(function () {
    mockCampusCodeResolver();
});

// =========================================================================
// InstallmentPushed fires after successful DNG push
// =========================================================================
it('dispatches InstallmentPushed event on successful push', function () {
    Event::fake([InstallmentPushed::class]);
    mockDngClientPushSuccess();
    $fix = createInstallmentPushFixture();

    freshPushAction()->handle($fix['charge']->id);

    Event::assertDispatched(InstallmentPushed::class, function (InstallmentPushed $e) use ($fix) {
        return $e->installment->finance_charge_id === $fix['charge']->id
            && $e->installment->installment_no === 1
            && $e->installment->status === FinanceChargeInstallment::STATUS_AWAITING_PAYMENT;
    });
});

// =========================================================================
// InstallmentPushed NOT fired on failed push (push throws before event)
// =========================================================================
it('does not dispatch InstallmentPushed when push fails', function () {
    Event::fake([InstallmentPushed::class]);
    mockDngClientPushFail();
    $fix = createInstallmentPushFixture();

    try {
        freshPushAction()->handle($fix['charge']->id);
    } catch (Throwable) {
        // expected
    }

    Event::assertNotDispatched(InstallmentPushed::class);
});

// =========================================================================
// ChargeFullySettled fires when last installment settles
// =========================================================================
it('dispatches ChargeFullySettled when the last installment is paid', function () {
    Event::fake([ChargeFullySettled::class]);
    mockDngClientPushSuccess();
    $fix = createInstallmentPushFixture();

    // Mark installment 1 paid manually (precondition).
    $fix['installments'][0]->update([
        'status' => FinanceChargeInstallment::STATUS_PAID,
        'paid_at' => now(),
    ]);

    // Push installment 2 → DNG record created.
    $i2 = freshPushAction()->handle($fix['charge']->id);

    // Settle installment 2 → no pending left → ChargeFullySettled fires.
    $dng = DngPaymentRequest::find($i2->dng_payment_request_id);
    $dng->update(['paid_at' => now()]);
    app(SettleInstallmentFromDngAction::class)->handle($dng);

    Event::assertDispatched(ChargeFullySettled::class, function (ChargeFullySettled $e) use ($fix) {
        return $e->charge->id === $fix['charge']->id;
    });
});

// =========================================================================
// ChargeFullySettled NOT fired when other pending installments remain
// =========================================================================
it('does not dispatch ChargeFullySettled when other installments remain pending', function () {
    Event::fake([ChargeFullySettled::class]);
    mockDngClientPushSuccess();
    $fix = createInstallmentPushFixture();

    // Push + settle only installment 1; installment 2 still pending.
    $i1 = freshPushAction()->handle($fix['charge']->id);
    $dng = DngPaymentRequest::find($i1->dng_payment_request_id);
    $dng->update(['paid_at' => now()]);
    app(SettleInstallmentFromDngAction::class)->handle($dng);

    Event::assertNotDispatched(ChargeFullySettled::class);
});

// =========================================================================
// InstallmentPushFailed fires after job exhausts all retries
// =========================================================================
it('dispatches InstallmentPushFailed when job exhausts all retries', function () {
    Event::fake([InstallmentPushFailed::class]);
    $fix = createInstallmentPushFixture();

    $job = new PushNextInstallmentJob($fix['charge']->id);
    $exception = new RuntimeException('DNG sustained outage');

    // Simulate Laravel calling failed() after final retry.
    $job->failed($exception);

    Event::assertDispatched(InstallmentPushFailed::class, function (InstallmentPushFailed $e) use ($fix) {
        return $e->financeChargeId === $fix['charge']->id
            && str_contains($e->errorMessage, 'DNG sustained outage');
    });
});
