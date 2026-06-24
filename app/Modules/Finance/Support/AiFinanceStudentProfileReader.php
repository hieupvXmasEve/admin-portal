<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use App\Modules\Finance\Queries\GetStudentBalanceQuery;
use App\Modules\Finance\Queries\Student360\GetStudent360StatusCardsQuery;
use App\Shared\Contracts\Finance\AiFinanceStudentProfileReader as AiFinanceStudentProfileReaderContract;

class AiFinanceStudentProfileReader implements AiFinanceStudentProfileReaderContract
{
    public function __construct(
        private readonly GetStudentBalanceQuery $balanceQuery,
        private readonly GetStudent360StatusCardsQuery $statusCardsQuery,
    ) {}

    public function financeSummary(int $studentId): array
    {
        $balance = $this->balanceQuery->handle($studentId);
        $cards = $this->statusCardsQuery->handle($studentId);

        return [
            'balance' => [
                'total_charges' => (float) ($balance['total_charges'] ?? 0),
                'total_credits' => (float) ($balance['total_credits'] ?? 0),
                'net_charges' => (float) ($balance['net_charges'] ?? 0),
                'total_paid' => (float) ($balance['total_paid'] ?? 0),
                'balance' => (float) ($balance['balance'] ?? 0),
                'unapplied_credit' => (float) ($balance['unapplied_credit'] ?? 0),
                'status' => (string) ($balance['status'] ?? 'unknown'),
            ],
            'dng' => $this->safeDngCard($cards['dng'] ?? []),
            'installments' => $this->safeInstallmentsCard($cards['installments'] ?? []),
            'exception' => $this->safeExceptionCard($cards['exception'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function safeDngCard(array $card): array
    {
        $request = is_array($card['request'] ?? null) ? $card['request'] : null;

        return [
            'has_active' => (bool) ($card['has_active'] ?? false),
            'request' => $request === null ? null : [
                'status' => $request['status'] ?? null,
                'item_id' => $request['item_id'] ?? null,
                'amount' => isset($request['amount']) ? (float) $request['amount'] : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function safeInstallmentsCard(array $card): array
    {
        $next = is_array($card['next'] ?? null) ? $card['next'] : null;

        return [
            'total' => (int) ($card['total'] ?? 0),
            'paid' => (int) ($card['paid'] ?? 0),
            'next' => $next === null ? null : [
                'installment_no' => (int) ($next['installment_no'] ?? 0),
                'due_date' => $next['due_date'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array<string, mixed>
     */
    private function safeExceptionCard(array $card): array
    {
        $blockingReasons = is_array($card['blocking_reasons'] ?? null) ? $card['blocking_reasons'] : [];

        return [
            'needs_review' => (bool) ($card['needs_review'] ?? false),
            'blocking_reason_count' => count($blockingReasons),
        ];
    }
}
