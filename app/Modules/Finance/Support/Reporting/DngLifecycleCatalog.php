<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Reporting;

use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;

/**
 * Vocabulary and pure classifiers for the DNG/Payment Lifecycle reporting lens
 * (FIN-REV-019).
 *
 * The row grain is `DNG/payment request`. This lens is campus-bound but is never
 * hard-filtered by the global Finance semester — related semester is evidence,
 * not an implicit filter. Every derived dimension (attention bucket, webhook
 * state, payment bridge, invoice state, allocation state, cancel/retry/error
 * state, and semester lineage) is defined here once so the query, summary,
 * request validation, and filter options can never drift apart.
 */
final class DngLifecycleCatalog
{
    /** A `pending` request older than this many minutes is stuck awaiting push. */
    public const PENDING_STALE_MINUTES = 60;

    // ---------------------------------------------------------------------
    // Attention buckets (accepted set from the product contract)
    // ---------------------------------------------------------------------

    public const BUCKET_FAILED_REQUEST = 'failed_request';

    public const BUCKET_WEBHOOK_PROBLEM = 'webhook_problem';

    public const BUCKET_PAID_UNINVOICED = 'paid_uninvoiced';

    public const BUCKET_PENDING_STALE = 'pending_stale';

    public const BUCKET_OVERDUE_PUSHED = 'overdue_pushed';

    // ---------------------------------------------------------------------
    // Payment bridge states
    // ---------------------------------------------------------------------

    public const BRIDGE_BRIDGED = 'bridged';

    public const BRIDGE_NOT_BRIDGED = 'not_bridged';

    // ---------------------------------------------------------------------
    // Webhook aggregate states (worst-first precedence)
    // ---------------------------------------------------------------------

    public const WEBHOOK_INVALID_CHECKSUM = 'invalid_checksum';

    public const WEBHOOK_MISMATCH = 'mismatch';

    public const WEBHOOK_FAILED = 'failed';

    public const WEBHOOK_PENDING = 'pending';

    public const WEBHOOK_OK = 'ok';

    public const WEBHOOK_NONE = 'none';

    // ---------------------------------------------------------------------
    // Invoice states
    // ---------------------------------------------------------------------

    public const INVOICE_INVOICED = 'invoiced';

    public const INVOICE_UNINVOICED = 'uninvoiced';

    public const INVOICE_NOT_APPLICABLE = 'not_applicable';

    // ---------------------------------------------------------------------
    // Allocation / reconciliation states
    // ---------------------------------------------------------------------

    public const ALLOCATION_RECONCILED = 'reconciled';

    public const ALLOCATION_PENDING = 'pending_reconciliation';

    public const ALLOCATION_NOT_APPLICABLE = 'not_applicable';

    // ---------------------------------------------------------------------
    // Cancel / retry / error states
    // ---------------------------------------------------------------------

    public const FLOW_NONE = 'none';

    public const FLOW_ERROR = 'error';

    public const FLOW_CANCELLED = 'cancelled';

    public const FLOW_RETRYING = 'retrying';

    // ---------------------------------------------------------------------
    // Related semester lineage states
    // ---------------------------------------------------------------------

    public const SEMESTER_SINGLE = 'single';

    public const SEMESTER_MULTI = 'multi';

    public const SEMESTER_UNKNOWN = 'unknown';

    /**
     * @return array<string, string>
     */
    public static function attentionBuckets(): array
    {
        return [
            self::BUCKET_FAILED_REQUEST => 'Yêu cầu thất bại',
            self::BUCKET_WEBHOOK_PROBLEM => 'Webhook lỗi/sai lệch',
            self::BUCKET_PAID_UNINVOICED => 'Đã thu, chưa xuất hóa đơn',
            self::BUCKET_PENDING_STALE => 'Chờ xử lý quá '.self::PENDING_STALE_MINUTES.' phút',
            self::BUCKET_OVERDUE_PUSHED => 'Đã đẩy DNG, quá hạn',
        ];
    }

    /**
     * @return list<string>
     */
    public static function attentionBucketKeys(): array
    {
        return array_keys(self::attentionBuckets());
    }

    public static function attentionBucketLabel(string $bucket): string
    {
        return self::attentionBuckets()[$bucket] ?? $bucket;
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            DngPaymentRequest::STATUS_PENDING => 'Chờ xử lý',
            DngPaymentRequest::STATUS_PUSHED_TO_DNG => 'Đã đẩy DNG',
            DngPaymentRequest::STATUS_PAID_UNINVOICED => 'Đã thu, chưa xuất HĐ',
            DngPaymentRequest::STATUS_PAID_INVOICED => 'Đã thu, đã xuất HĐ',
            DngPaymentRequest::STATUS_RECONCILED => 'Đã đối soát',
            DngPaymentRequest::STATUS_FAILED => 'Thất bại',
            DngPaymentRequest::STATUS_CANCELLED => 'Đã hủy',
            DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG => 'Đã hủy trên DNG',
        ];
    }

    /**
     * @return list<string>
     */
    public static function statusKeys(): array
    {
        return array_keys(self::statuses());
    }

    public static function statusLabel(string $status): string
    {
        return self::statuses()[$status] ?? $status;
    }

    /**
     * @return array<string, string>
     */
    public static function paymentBridgeStates(): array
    {
        return [
            self::BRIDGE_BRIDGED => 'Đã tạo Payment',
            self::BRIDGE_NOT_BRIDGED => 'Chưa tạo Payment',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function webhookStates(): array
    {
        return [
            self::WEBHOOK_INVALID_CHECKSUM => 'Sai checksum',
            self::WEBHOOK_MISMATCH => 'Sai lệch dữ liệu',
            self::WEBHOOK_FAILED => 'Xử lý thất bại',
            self::WEBHOOK_PENDING => 'Đang chờ xử lý',
            self::WEBHOOK_OK => 'Hợp lệ',
            self::WEBHOOK_NONE => 'Chưa có webhook',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function invoiceStates(): array
    {
        return [
            self::INVOICE_INVOICED => 'Đã xuất hóa đơn',
            self::INVOICE_UNINVOICED => 'Chưa xuất hóa đơn',
            self::INVOICE_NOT_APPLICABLE => 'Chưa phát sinh',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function allocationStates(): array
    {
        return [
            self::ALLOCATION_RECONCILED => 'Đã đối soát',
            self::ALLOCATION_PENDING => 'Chờ đối soát',
            self::ALLOCATION_NOT_APPLICABLE => 'Chưa phát sinh',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function flowStates(): array
    {
        return [
            self::FLOW_NONE => 'Bình thường',
            self::FLOW_ERROR => 'Có lỗi',
            self::FLOW_CANCELLED => 'Đã hủy',
            self::FLOW_RETRYING => 'Đang thử lại',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function semesterStates(): array
    {
        return [
            self::SEMESTER_SINGLE => 'Một học kỳ',
            self::SEMESTER_MULTI => 'Nhiều học kỳ',
            self::SEMESTER_UNKNOWN => 'Không xác định',
        ];
    }

    public static function paymentBridgeLabel(string $state): string
    {
        return self::paymentBridgeStates()[$state] ?? $state;
    }

    public static function webhookStateLabel(string $state): string
    {
        return self::webhookStates()[$state] ?? $state;
    }

    public static function invoiceStateLabel(string $state): string
    {
        return self::invoiceStates()[$state] ?? $state;
    }

    public static function allocationStateLabel(string $state): string
    {
        return self::allocationStates()[$state] ?? $state;
    }

    public static function flowStateLabel(string $state): string
    {
        return self::flowStates()[$state] ?? $state;
    }

    public static function semesterStateLabel(string $state): string
    {
        return self::semesterStates()[$state] ?? $state;
    }

    // ---------------------------------------------------------------------
    // Pure classifiers
    // ---------------------------------------------------------------------

    /**
     * Collapse a request's webhook events into a single worst-case state.
     * Problem states (invalid checksum, mismatch, failed) outrank pending,
     * which outranks a clean processed event; no events at all is `none`.
     *
     * @param  list<array{is_valid_checksum: bool, processing_status: string}>  $events
     */
    public static function deriveWebhookState(array $events): string
    {
        if ($events === []) {
            return self::WEBHOOK_NONE;
        }

        $hasInvalidChecksum = false;
        $hasMismatch = false;
        $hasFailed = false;
        $hasPending = false;
        $hasProcessed = false;

        foreach ($events as $event) {
            if ($event['is_valid_checksum'] === false) {
                $hasInvalidChecksum = true;
            }

            switch ($event['processing_status']) {
                case DngWebhookEvent::STATUS_MISMATCH:
                    $hasMismatch = true;
                    break;
                case DngWebhookEvent::STATUS_FAILED_RETRYABLE:
                case DngWebhookEvent::STATUS_FAILED_TERMINAL:
                    $hasFailed = true;
                    break;
                case DngWebhookEvent::STATUS_PENDING:
                case DngWebhookEvent::STATUS_RECEIVED:
                case DngWebhookEvent::STATUS_PROCESSING:
                    $hasPending = true;
                    break;
                case DngWebhookEvent::STATUS_PROCESSED:
                    $hasProcessed = true;
                    break;
            }
        }

        return match (true) {
            $hasInvalidChecksum => self::WEBHOOK_INVALID_CHECKSUM,
            $hasMismatch => self::WEBHOOK_MISMATCH,
            $hasFailed => self::WEBHOOK_FAILED,
            $hasPending => self::WEBHOOK_PENDING,
            $hasProcessed => self::WEBHOOK_OK,
            default => self::WEBHOOK_OK,
        };
    }

    /**
     * A webhook state that belongs to the "failed / invalid / mismatched"
     * attention bucket.
     */
    public static function isWebhookProblem(string $state): bool
    {
        return in_array($state, [
            self::WEBHOOK_INVALID_CHECKSUM,
            self::WEBHOOK_MISMATCH,
            self::WEBHOOK_FAILED,
        ], true);
    }

    public static function derivePaymentBridge(?int $paymentId): string
    {
        return $paymentId !== null ? self::BRIDGE_BRIDGED : self::BRIDGE_NOT_BRIDGED;
    }

    public static function deriveInvoiceState(string $status, ?string $invoiceSerialNumber): string
    {
        if (filled($invoiceSerialNumber) || in_array($status, [
            DngPaymentRequest::STATUS_PAID_INVOICED,
            DngPaymentRequest::STATUS_RECONCILED,
        ], true)) {
            return self::INVOICE_INVOICED;
        }

        if ($status === DngPaymentRequest::STATUS_PAID_UNINVOICED) {
            return self::INVOICE_UNINVOICED;
        }

        return self::INVOICE_NOT_APPLICABLE;
    }

    public static function deriveAllocationState(string $status): string
    {
        if ($status === DngPaymentRequest::STATUS_RECONCILED) {
            return self::ALLOCATION_RECONCILED;
        }

        if (in_array($status, [
            DngPaymentRequest::STATUS_PAID_UNINVOICED,
            DngPaymentRequest::STATUS_PAID_INVOICED,
        ], true)) {
            return self::ALLOCATION_PENDING;
        }

        return self::ALLOCATION_NOT_APPLICABLE;
    }

    /**
     * Classify the cancel/retry/error posture of a request. Cancelled outranks
     * error, which outranks an active retry; otherwise the request is clean.
     */
    public static function deriveFlowState(string $status, bool $hasError, bool $isRetrying): string
    {
        if (in_array($status, [
            DngPaymentRequest::STATUS_CANCELLED,
            DngPaymentRequest::STATUS_CANCEL_PUSHED_TO_DNG,
        ], true)) {
            return self::FLOW_CANCELLED;
        }

        if ($status === DngPaymentRequest::STATUS_FAILED || $hasError) {
            return self::FLOW_ERROR;
        }

        if ($isRetrying) {
            return self::FLOW_RETRYING;
        }

        return self::FLOW_NONE;
    }

    /**
     * @param  list<int>  $semesterIds  Distinct semester ids resolved for the request.
     */
    public static function relatedSemesterState(array $semesterIds): string
    {
        $distinct = array_values(array_unique($semesterIds));

        return match (true) {
            $distinct === [] => self::SEMESTER_UNKNOWN,
            count($distinct) === 1 => self::SEMESTER_SINGLE,
            default => self::SEMESTER_MULTI,
        };
    }

    /**
     * Compute the attention buckets a request falls into. A request can sit in
     * several buckets at once; the caller keeps the full list plus a derived
     * `needs_attention` flag.
     *
     * @return list<string>
     */
    public static function attentionBucketsFor(
        string $status,
        ?\DateTimeInterface $createdAt,
        ?\DateTimeInterface $dueDate,
        string $webhookState,
        ?\DateTimeInterface $now = null,
    ): array {
        $now ??= now();
        $buckets = [];

        if ($status === DngPaymentRequest::STATUS_FAILED) {
            $buckets[] = self::BUCKET_FAILED_REQUEST;
        }

        if (self::isWebhookProblem($webhookState)) {
            $buckets[] = self::BUCKET_WEBHOOK_PROBLEM;
        }

        if ($status === DngPaymentRequest::STATUS_PAID_UNINVOICED) {
            $buckets[] = self::BUCKET_PAID_UNINVOICED;
        }

        if (
            $status === DngPaymentRequest::STATUS_PENDING
            && $createdAt !== null
            && $createdAt <= (clone $now)->modify('-'.self::PENDING_STALE_MINUTES.' minutes')
        ) {
            $buckets[] = self::BUCKET_PENDING_STALE;
        }

        if (
            $status === DngPaymentRequest::STATUS_PUSHED_TO_DNG
            && $dueDate !== null
            && $dueDate < $now
        ) {
            $buckets[] = self::BUCKET_OVERDUE_PUSHED;
        }

        return $buckets;
    }
}
