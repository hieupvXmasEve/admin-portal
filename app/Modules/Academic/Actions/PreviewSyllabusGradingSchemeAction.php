<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Delivery\Support\Grading\GradingCalculatorResolver;
use App\Modules\Academic\Delivery\Support\Grading\GradingSchemeValidator;
use Throwable;

/**
 * Runs a grading scheme against sample component scores without persisting
 * anything, for the admin preview UI. It reuses the exact validator and
 * calculator contracts used at runtime, so a preview matches a real grade.
 */
final class PreviewSyllabusGradingSchemeAction
{
    public function __construct(
        private readonly GradingCalculatorResolver $resolver,
        private readonly GradingSchemeValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $scheme
     * @param  array<string, float|int|null>  $componentScores  component code → percentage (0-100)
     * @return array{valid: bool, errors?: array<int, string>, scale?: string|null, final_grade?: string, final_percentage?: float|null, grade_points?: float, passed?: bool, breakdown?: array<string, mixed>}
     */
    public function execute(array $scheme, array $componentScores): array
    {
        $errors = $this->validator->validate($scheme);

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors];
        }

        $scores = [];
        foreach ($componentScores as $code => $value) {
            $scores[(string) $code] = $value === null ? null : (float) $value;
        }

        try {
            $result = $this->resolver->resolve($scheme)->calculate($scores, $scheme);
        } catch (Throwable $exception) {
            return ['valid' => false, 'errors' => [$exception->getMessage()]];
        }

        return [
            'valid' => true,
            'scale' => $scheme['scale'] ?? null,
            'final_grade' => $result->finalGrade,
            'final_percentage' => $result->finalPercentage,
            'grade_points' => $result->gradePoints,
            'passed' => $result->passed,
            'breakdown' => $result->gradeBreakdown,
        ];
    }
}
