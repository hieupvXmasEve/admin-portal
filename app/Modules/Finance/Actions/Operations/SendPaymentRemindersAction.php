<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Operations;

class SendPaymentRemindersAction
{
    public static function run(array $data): array
    {
        // Implementation placeholder
        return [
            'sent_count' => count($data['invoice_ids'] ?? []),
            'message' => 'Reminders sent successfully',
        ];
    }
}
