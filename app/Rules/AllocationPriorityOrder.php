<?php

declare(strict_types=1);

namespace App\Rules;

use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllocationPriorityOrder implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        $expected = ObligationTypeRegistry::allocationPriorityOrder();
        $actual = array_values($value);

        if (count($actual) !== count($expected)) {
            $fail('The :attribute must include every debit fee type exactly once.');

            return;
        }

        foreach ($actual as $item) {
            if (! is_string($item) || $item === '') {
                $fail('The :attribute may only contain debit fee type codes.');

                return;
            }
        }

        if (count(array_unique($actual)) !== count($actual)) {
            $fail('The :attribute must not contain duplicate fee types.');

            return;
        }

        $expectedSorted = $expected;
        $actualSorted = $actual;
        sort($expectedSorted);
        sort($actualSorted);

        if ($actualSorted !== $expectedSorted) {
            $fail('The :attribute must be a permutation of the debit fee types.');
        }
    }
}
