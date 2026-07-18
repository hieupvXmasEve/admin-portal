<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Dng;

use App\Modules\Finance\Models\DngReceiptException;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;

class ListDngReceiptExceptionsQuery
{
    public function __construct(private readonly StudentReferenceReader $studentReferences) {}

    /** @return list<array<string, mixed>> */
    public function handle(): array
    {
        return DngReceiptException::query()
            ->with('dngPaymentRequest')
            ->where('status', DngReceiptException::STATUS_OPEN)
            ->latest('id')
            ->get()
            ->map(function (DngReceiptException $exception): array {
                $studentId = $exception->dngPaymentRequest?->student_id;
                $student = $studentId === null ? null : $this->studentReferences->find((int) $studentId);

                return [
                    'id' => $exception->id,
                    'exception_type' => $exception->exception_type,
                    'provider_payment_id' => $exception->provider_payment_id,
                    'mismatch_reasons' => $exception->mismatch_reasons,
                    'affected_scope' => $exception->affected_scope,
                    'raw_provider_evidence' => $exception->raw_provider_evidence,
                    'request_id' => $exception->dng_payment_request_id,
                    'student_name' => $student?->fullName,
                    'outcome' => $this->outcome($exception),
                    'next_action' => $this->nextAction($exception),
                ];
            })
            ->all();
    }

    private function outcome(DngReceiptException $exception): string
    {
        return match ($exception->exception_type) {
            'unknown_collection_outcome' => 'Kết quả hủy chưa xác định',
            'cancel_confirmed' => 'Đã hủy xác nhận',
            'cancel_rejected' => 'Hủy bị provider từ chối',
            'payment_during_cancellation', 'payment_after_cancellation' => 'Đã thanh toán trong hoặc sau khi hủy',
            default => 'Cần kiểm tra receipt',
        };
    }

    private function nextAction(DngReceiptException $exception): string
    {
        return match ($exception->exception_type) {
            'unknown_collection_outcome' => 'Đối soát DNG trước khi thu lại hoặc giải phóng hold.',
            'cancel_confirmed' => 'Không cần thu lại từ request này; obligation vẫn còn hiệu lực nếu chưa được xử lý riêng.',
            'cancel_rejected' => 'Xác minh provider rồi retry hủy hoặc giữ collection đang hoạt động.',
            'payment_during_cancellation', 'payment_after_cancellation' => 'Đối soát Payment đã ghi nhận và xử lý số dư/đích thu nếu cần.',
            default => 'Đối soát provider evidence trước khi thực hiện hành động thu tiếp.',
        };
    }
}
