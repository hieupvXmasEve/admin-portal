<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Batch;

enum BatchJobType: string
{
    case ChargeGeneration = 'charge_generation';
    case DngPush = 'dng_push';
    case Reminder = 'reminder';

    /**
     * Cache-key namespace for issued preview tokens.
     */
    public function cacheNamespace(): string
    {
        return 'finance-batch-preview:'.$this->value;
    }
}