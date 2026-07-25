<?php

declare(strict_types=1);

use App\Modules\Finance\Models\DngReceiptException;
use App\Modules\Finance\Policies\DngReceiptExceptionPolicy;
use Illuminate\Support\Facades\Gate;

it('registers the receipt exception policy for the Finance model', function (): void {
    expect(Gate::getPolicyFor(DngReceiptException::class))
        ->toBeInstanceOf(DngReceiptExceptionPolicy::class);
});
