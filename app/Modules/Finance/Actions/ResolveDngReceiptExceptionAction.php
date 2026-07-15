<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\DngReceiptException;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\SettlementMutationGuard;
use Illuminate\Support\Facades\DB;

class ResolveDngReceiptExceptionAction
{
    public function __construct(
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
        private readonly SettlementMutationGuard $settlementMutationGuard,
    ) {}

    /** @param array{exception: DngReceiptException, user_id: int} $data */
    public static function run(array $data): DngReceiptException
    {
        return app(self::class)->handle($data['exception'], $data['user_id']);
    }

    public function handle(DngReceiptException $exception, int $userId): DngReceiptException
    {
        $linkedRequest = $exception->dngPaymentRequest;
        if ($linkedRequest !== null) {
            $billingAccountId = $linkedRequest->billing_account_id
                ?? $this->billingAccountProvisioner->forStudent((int) $linkedRequest->student_id)->id;

            return $this->settlementMutationGuard->handleIfChanged(
                (int) $billingAccountId,
                function ($_billingAccount, \Closure $markChanged) use ($exception, $userId, $billingAccountId): DngReceiptException {
                    return DB::transaction(function () use ($exception, $userId, $markChanged, $billingAccountId): DngReceiptException {
                        $exception = DngReceiptException::query()->lockForUpdate()->findOrFail($exception->id);
                        if ($exception->status === DngReceiptException::STATUS_RESOLVED) {
                            return $exception;
                        }

                        $exception->update([
                            'status' => DngReceiptException::STATUS_RESOLVED,
                            'resolved_at' => now(),
                            'resolved_by_user_id' => $userId,
                        ]);
                        $markChanged();

                        /** @var DngPaymentRequest|null $request */
                        $request = $exception->dngPaymentRequest;
                        if ($request !== null && $request->billing_account_id === null) {
                            $request->update(['billing_account_id' => $billingAccountId]);
                            $markChanged();
                        }

                        if ($request !== null
                            && $request->status === DngPaymentRequest::STATUS_NEEDS_REVIEW
                            && ! DngReceiptException::query()->where('dng_payment_request_id', $request->id)->where('status', DngReceiptException::STATUS_OPEN)->exists()) {
                            $request->update([
                                'status' => DngPaymentRequest::STATUS_FAILED,
                                'active_slot_key' => null,
                                'error_message' => null,
                            ]);
                            $markChanged();
                        }

                        return $exception->fresh();
                    });
                },
            );
        }

        return DB::transaction(function () use ($exception, $userId): DngReceiptException {
            $exception = DngReceiptException::query()->lockForUpdate()->findOrFail($exception->id);
            if ($exception->status === DngReceiptException::STATUS_RESOLVED) {
                return $exception;
            }

            $exception->update([
                'status' => DngReceiptException::STATUS_RESOLVED,
                'resolved_at' => now(),
                'resolved_by_user_id' => $userId,
            ]);

            return $exception->fresh();
        });
    }
}
