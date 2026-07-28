<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support\Grading;

use App\Modules\Academic\Delivery\Support\Grading\Contracts\GradingCalculator;
use InvalidArgumentException;

class GradingCalculatorResolver
{
    public function __construct(
        private readonly DefaultWeightedPercentageCalculator $defaultCalculator,
        private readonly MetropoliaV1Calculator $metropoliaV1Calculator,
        private readonly MetropoliaV2Calculator $metropoliaV2Calculator,
    ) {}

    /**
     * @param  array<string, mixed>|null  $scheme
     */
    public function resolve(?array $scheme): GradingCalculator
    {
        $engine = $scheme['engine'] ?? null;

        return match ($engine) {
            null, 'default_weighted_percentage' => $this->defaultCalculator,
            'metropolia_v1' => $this->metropoliaV1Calculator,
            'metropolia_v2' => $this->metropoliaV2Calculator,
            default => throw new InvalidArgumentException("Unknown grading engine: {$engine}"),
        };
    }
}
