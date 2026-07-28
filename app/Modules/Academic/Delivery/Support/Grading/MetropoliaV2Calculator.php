<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support\Grading;

use App\Modules\Academic\Delivery\Support\Grading\Contracts\GradingCalculator;

/**
 * Formula-based Metropolia grading engine (metropolia_v2).
 *
 * Where metropolia_v1 sums independently converted component grades, v2 evaluates
 * a single arithmetic expression over the raw component percentages. This faithfully
 * reproduces Metropolia rules that use a weighted total with a global offset — e.g.
 * Cloud Computing's `(LAB + QUIZ + EXAM/2 - 40)/10` — which the summation engine
 * could only approximate.
 *
 * Scheme JSON:
 * {
 *   "engine": "metropolia_v2",
 *   "scale": "0-5",
 *   "formula": "(LAB + QUIZ + EXAM / 2 - 40) / 10",  // variables = component codes; scores are 0-100
 *   "rounding_stage": "after_total" | "none",          // default after_total (round to nearest integer)
 *   "clamp_min": 0,                                      // default 0
 *   "clamp_max": 5,                                      // default 5
 *   "pass_requirements": [ { "code": "ASSIGNMENT", "min_pct": 40 } ],  // optional course-level gates
 *   "components": [ { "code": "LAB", "label": "Labs" }, ... ]          // declares variables + audit labels
 * }
 *
 * The formula is evaluated by {@see SafeArithmeticEvaluator}, a whitelist parser —
 * never PHP eval — so operator-authored schemes cannot execute arbitrary code.
 */
class MetropoliaV2Calculator implements GradingCalculator
{
    private const DEFAULT_CLAMP_MIN = 0;

    private const DEFAULT_CLAMP_MAX = 5;

    public function __construct(
        private readonly SafeArithmeticEvaluator $evaluator,
    ) {}

    public function calculate(array $componentScores, ?array $scheme): GradingResult
    {
        $scheme ??= [];
        $components = $scheme['components'] ?? [];
        $formula = (string) ($scheme['formula'] ?? '0');

        $variables = $this->buildVariableMap($components, $componentScores);
        $componentResults = $this->buildComponentResults($variables);

        $rawFg = $this->evaluator->evaluate($formula, $variables);
        $clampMin = (int) ($scheme['clamp_min'] ?? self::DEFAULT_CLAMP_MIN);
        $clampMax = (int) ($scheme['clamp_max'] ?? self::DEFAULT_CLAMP_MAX);

        $rounded = ($scheme['rounding_stage'] ?? 'after_total') === 'none'
            ? $rawFg
            : (float) round($rawFg);

        $fg = (int) max($clampMin, min($clampMax, (int) $rounded));

        $gateFailures = $this->evaluateGates($scheme['pass_requirements'] ?? [], $variables);
        $gatesPassed = $gateFailures === [];

        $passed = $gatesPassed && $fg >= 1;
        $finalGrade = $passed ? (string) $fg : '0';
        $finalPercentage = $clampMax > 0 ? round($rawFg / $clampMax * 100, 2) : null;

        return new GradingResult(
            finalPercentage: $finalPercentage,
            finalGrade: $finalGrade,
            gradePoints: (float) $fg,
            passed: $passed,
            gradeBreakdown: [
                'engine' => 'metropolia_v2',
                'scale' => $scheme['scale'] ?? '0-5',
                'formula' => $formula,
                'components' => $componentResults,
                'fg_raw' => $rawFg,
                'fg_rounded' => $fg,
                'gates_passed' => $gatesPassed,
                'gate_failures' => $gateFailures,
                'final_grade' => $finalGrade,
            ],
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @param  array<string, float|null>  $componentScores
     * @return array<string, float>
     */
    private function buildVariableMap(array $components, array $componentScores): array
    {
        $variables = [];

        foreach ($components as $component) {
            $code = $component['code'] ?? null;

            if (is_string($code) && $code !== '') {
                $variables[$code] = (float) ($componentScores[$code] ?? 0.0);
            }
        }

        return $variables;
    }

    /**
     * @param  array<string, float>  $variables
     * @return array<string, array{score_pct: float}>
     */
    private function buildComponentResults(array $variables): array
    {
        $results = [];

        foreach ($variables as $code => $score) {
            $results[$code] = ['score_pct' => $score];
        }

        return $results;
    }

    /**
     * @param  array<int, array<string, mixed>>  $requirements
     * @param  array<string, float>  $variables
     * @return array<int, array{code: string, score: float, required: float}>
     */
    private function evaluateGates(array $requirements, array $variables): array
    {
        $failures = [];

        foreach ($requirements as $requirement) {
            $code = $requirement['code'] ?? null;

            if (! is_string($code)) {
                continue;
            }

            $minPct = (float) ($requirement['min_pct'] ?? 0);
            $score = $variables[$code] ?? 0.0;

            if ($score < $minPct) {
                $failures[] = ['code' => $code, 'score' => $score, 'required' => $minPct];
            }
        }

        return $failures;
    }
}
