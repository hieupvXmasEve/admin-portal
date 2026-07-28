<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support\Grading\Contracts;

use App\Modules\Academic\Delivery\Support\Grading\GradingResult;

interface GradingCalculator
{
    /**
     * @param  array<string, float|null>  $componentScores  component code → aggregated percentage (0-100)
     * @param  array<string, mixed>|null  $scheme  raw grading_scheme JSON (null for default)
     */
    public function calculate(array $componentScores, ?array $scheme): GradingResult;
}
