<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Models\Form;

final readonly class SurveyAggregateConfig
{
    /**
     * @param  ?array<int, string>  $questionCodes  null = all rating questions (default)
     */
    public function __construct(
        public ?array $questionCodes,
        public int $positiveMin = 4,
        public int $negativeMax = 2,
        public bool $isCustom = false,
    ) {}

    public static function fromForm(?Form $form): self
    {
        $config = $form?->aggregate_config['overall'] ?? null;

        if (! is_array($config)) {
            return new self(questionCodes: null);
        }

        $codes = $config['question_codes'] ?? null;
        $codes = is_array($codes) && $codes !== [] ? array_values(array_map('strval', $codes)) : null;

        $positiveMin = (int) ($config['thresholds']['positive_min'] ?? 4);
        $negativeMax = (int) ($config['thresholds']['negative_max'] ?? 2);

        return new self(
            questionCodes: $codes,
            positiveMin: $positiveMin,
            negativeMax: $negativeMax,
            isCustom: $codes !== null,
        );
    }

    public function isPositive(float $value): bool
    {
        return $value >= $this->positiveMin;
    }

    public function isNegative(float $value): bool
    {
        return $value <= $this->negativeMax;
    }
}
