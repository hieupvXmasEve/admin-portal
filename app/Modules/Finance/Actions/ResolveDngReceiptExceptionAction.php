<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\DngReceiptException;
use Illuminate\Support\Facades\DB;

class ResolveDngReceiptExceptionAction
{
    /** @param array{exception: DngReceiptException, user_id: int} $data */
    public static function run(array $data): DngReceiptException
    {
        return app(self::class)->handle($data['exception'], $data['user_id']);
    }

    public function handle(DngReceiptException $exception, int $userId): DngReceiptException
    {
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

            $request = $exception->dngPaymentRequest;
            if ($request !== null
                && $request->status === DngPaymentRequest::STATUS_NEEDS_REVIEW
                && ! DngReceiptException::query()->where('dng_payment_request_id', $request->id)->where('status', DngReceiptException::STATUS_OPEN)->exists()) {
                $request->update([
                    'status' => DngPaymentRequest::STATUS_FAILED,
                    'active_slot_key' => null,
                    'error_message' => null,
                ]);
            }

            return $exception->fresh();
        });
    }
}
