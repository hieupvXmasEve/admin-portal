<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Exceptions\StudentDngPaymentAccessUnavailable;
use App\Modules\Finance\Dng\Exceptions\StudentDngPaymentRequestNotFound;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Support\SettlementPosition\Money;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class CreateStudentDngPaymentAccessAction
{
    private const PAYMENT_METHODS = ['qr', 'installment'];

    public function __construct(
        private readonly DngCampusCodeResolver $campusCodeResolver,
        private readonly DngPaymentService $dngPaymentService,
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly StudentReferenceReader $studentReferences,
    ) {}

    /**
     * @param  array{student_id: int, payment_method: string, dng_request_id?: int|null}  $data
     * @return array<string, mixed>
     */
    public static function run(array $data): array
    {
        $studentId = isset($data['student_id']) ? (int) $data['student_id'] : 0;
        if ($studentId <= 0) {
            throw new \InvalidArgumentException('A student is required for DNG payment access.');
        }

        return app(self::class)->handle(
            $studentId,
            (string) $data['payment_method'],
            isset($data['dng_request_id']) ? (int) $data['dng_request_id'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(int $studentId, string $paymentMethod, ?int $dngRequestId = null): array
    {
        if (! in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            throw new \InvalidArgumentException("Unsupported student DNG payment method [{$paymentMethod}].");
        }

        $student = $this->studentReferences->find($studentId);
        if ($student === null) {
            throw new StudentDngPaymentAccessUnavailable;
        }

        $campusCode = $this->campusCode($student);
        $billingAccount = BillingAccount::query()
            ->where('student_id', $student->id)
            ->first();

        if ($billingAccount === null) {
            throw new StudentDngPaymentAccessUnavailable;
        }

        $scopedQuery = DngPaymentRequest::query()
            ->where('student_id', $student->id)
            ->where('billing_account_id', $billingAccount->id)
            ->where('provider_rail', 'dng')
            ->where('campus_code', $campusCode)
            ->whereNull('payment_id');

        if ($dngRequestId !== null) {
            $requests = $scopedQuery
                ->whereKey($dngRequestId)
                ->whereIn('status', $this->activeStatuses())
                ->get();

            if ($requests->count() !== 1) {
                throw new StudentDngPaymentRequestNotFound;
            }
        } else {
            $requests = $scopedQuery
                ->whereIn('status', $this->activeStatuses())
                ->orderBy('fee_type')
                ->orderByDesc('created_at')
                ->get();

            $this->assertOneActiveRequestPerFeeType($requests);

            if ($requests->isEmpty() || $requests->contains(fn (DngPaymentRequest $request): bool => $request->status !== DngPaymentRequest::STATUS_PUSHED_TO_DNG)) {
                throw new StudentDngPaymentAccessUnavailable;
            }

            // The current FeeTypes[] provider response has no transaction-to-ItemId
            // map. Never expose a combined payment page until that mapping is proven.
            if ($requests->count() > 1) {
                throw new StudentDngPaymentAccessUnavailable;
            }
        }

        $this->assertRequestIsSafe($requests->firstOrFail(), $student, $campusCode);
        $anchor = $requests->firstOrFail();
        $feeTypes = $requests->pluck('fee_type')->values()->all();
        $providerResponse = $paymentMethod === 'qr'
            ? $this->dngPaymentService->createQrAccess($anchor, $feeTypes)
            : $this->dngPaymentService->createInstallmentAccess($anchor, $feeTypes);
        $providerData = is_array($providerResponse['data'] ?? null) ? $providerResponse['data'] : [];

        $access = [
            'payment_method' => $paymentMethod,
            'payment_url' => $this->paymentUrl($providerResponse),
            'provider_response' => $providerResponse,
            'selection_mode' => $dngRequestId === null ? 'all_active_fee_types' : 'single_fee_type',
            'fee_types' => $feeTypes,
            'request_map' => $requests->map(fn (DngPaymentRequest $request): array => [
                'dng_request_id' => $request->id,
                'fee_type' => $request->fee_type,
                'item_id' => $request->item_id,
                'amount' => (float) $request->amount,
            ])->values()->all(),
            'correlation' => [
                'status' => 'exact_local_request',
                'provider_transaction_id' => $providerData['TransactionID']
                    ?? $providerData['PaymentId']
                    ?? null,
            ],
        ];

        if ($dngRequestId !== null) {
            $access['dng_request_id'] = $anchor->id;
            $access['status'] = $anchor->status;
        }

        return $access;
    }

    /** @return list<string> */
    private function activeStatuses(): array
    {
        return [
            DngPaymentRequest::STATUS_PENDING,
            DngPaymentRequest::STATUS_PUSHED_TO_DNG,
            DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
            DngPaymentRequest::STATUS_NEEDS_REVIEW,
        ];
    }

    /** @param Collection<int, DngPaymentRequest> $requests */
    private function assertOneActiveRequestPerFeeType(Collection $requests): void
    {
        if ($requests->groupBy('fee_type')->contains(fn (Collection $feeTypeRequests): bool => $feeTypeRequests->count() > 1)) {
            throw new StudentDngPaymentAccessUnavailable;
        }
    }

    private function assertRequestIsSafe(DngPaymentRequest $request, StudentReference $student, string $campusCode): void
    {
        if ($request->student_id !== $student->id
            || $request->billing_account_id === null
            || $request->provider_rail !== 'dng'
            || $request->campus_code !== $campusCode
            || $request->student_code !== $student->studentCode
            || $request->status !== DngPaymentRequest::STATUS_PUSHED_TO_DNG
            || blank($request->item_id)
            || (float) $request->amount <= 0) {
            throw new StudentDngPaymentAccessUnavailable;
        }

        $targetLineIds = $request->reservationTargets()
            ->pluck('invoice_line_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($targetLineIds === []) {
            return;
        }

        $position = $this->settlementPositionReader->forPayableLines($targetLineIds);
        $expectedRemaining = Money::vnd((string) $request->amount);

        if (! $position->isValid()
            || $position->amounts === null
            || $position->amounts->remaining->minor_amount !== $expectedRemaining->minor_amount) {
            throw new StudentDngPaymentAccessUnavailable;
        }
    }

    private function campusCode(StudentReference $student): string
    {
        try {
            return $this->campusCodeResolver->requireForCampusId($student->campusId);
        } catch (ValidationException) {
            throw new StudentDngPaymentAccessUnavailable;
        }
    }

    /** @param array<string, mixed> $providerResponse */
    private function paymentUrl(array $providerResponse): ?string
    {
        $providerData = is_array($providerResponse['data'] ?? null) ? $providerResponse['data'] : [];

        $paymentUrl = $providerData['PaymentUrl']
            ?? $providerData['payment_url']
            ?? $providerData['LinkQRCode']
            ?? null;

        return is_string($paymentUrl) ? $paymentUrl : null;
    }
}
