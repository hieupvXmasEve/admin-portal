<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support\Grading;

use App\Modules\Academic\Support\Grading\Contracts\GradingCalculator;

/**
 * Metropolia-style grading engine (metropolia_v1).
 *
 * Scheme JSON structure:
 * {
 *   "engine": "metropolia_v1",
 *   "scale": "0-5" | "pass_fail",
 *   "components": [
 *     {
 *       "code": "ASSIGNMENT",
 *       "gate": { "min_pct": 40 },        // optional — fails course if not met
 *       "conversion": {
 *         "type": "linear",               // see CONVERSION TYPES below
 *         ...
 *       }
 *     }
 *   ]
 * }
 *
 * CONVERSION TYPES
 * ----------------
 * linear      : { "min_pct": 40, "max_pct": 88, "min_grade": 1, "max_grade": 5 }
 *               Linear interpolation from (min_pct, min_grade) to (max_pct, max_grade).
 *               Scores below min_pct yield 0. Scores above max_pct yield max_grade.
 *
 * threshold   : { "steps": [{"min_pct": 55, "grade": 1}, {"min_pct": 70, "grade": 2}] }
 *               Highest grade whose min_pct is met. 0 if none met.
 *
 * direct      : { "max_grade": 5 }
 *               Raw score / 100 * max_grade (score treated as %).
 *
 * pass_fail   : { "min_pct": 80 }
 *               Course-level pass/fail — no numeric FG contribution.
 *               If gate is also set, gate takes precedence as failure signal.
 *
 * null/absent : Component has no FG contribution (gate-only component).
 *
 * FINAL GRADE
 * -----------
 * FG = sum of all component converted grades, rounded to nearest integer.
 * Clamped to [0, 5] for "0-5" scale.
 * For "pass_fail" scale: "P" / "F" — no numeric FG.
 */
class MetropoliaV1Calculator implements GradingCalculator
{
    public function calculate(array $componentScores, ?array $scheme): GradingResult
    {
        $scale = $scheme['scale'] ?? '0-5';
        $components = $scheme['components'] ?? [];

        $componentResults = [];
        $gateFailures = [];
        $fgSum = 0.0;

        foreach ($components as $comp) {
            $code = $comp['code'];
            $score = $componentScores[$code] ?? null;
            $gateDef = $comp['gate'] ?? null;
            $convDef = $comp['conversion'] ?? null;

            // Gate check: if gate defined and score doesn't meet it → fail
            if ($gateDef !== null && $score !== null) {
                $minPct = (float) $gateDef['min_pct'];
                if ($score < $minPct) {
                    $gateFailures[] = [
                        'code' => $code,
                        'score' => $score,
                        'required' => $minPct,
                    ];
                }
            }

            // Component conversion
            $convertedGrade = null;
            if ($convDef !== null && $score !== null) {
                $convertedGrade = $this->convertScore($score, $convDef);
                $fgSum += $convertedGrade;
            }

            $componentResults[$code] = [
                'score_pct' => $score,
                'converted_grade' => $convertedGrade,
                'gate_met' => $gateDef === null
                    ? null
                    : ($score !== null && $score >= (float) $gateDef['min_pct']),
            ];
        }

        $gatesPassed = empty($gateFailures);

        if ($scale === 'pass_fail') {
            return $this->buildPassFailResult($gatesPassed, $componentResults, $gateFailures, $scheme);
        }

        return $this->buildNumericResult($fgSum, $gatesPassed, $componentResults, $gateFailures, $scheme);
    }

    /**
     * @param  array<string, mixed>  $convDef
     */
    private function convertScore(float $score, array $convDef): float
    {
        return match ($convDef['type']) {
            'linear' => $this->convertLinear($score, $convDef),
            'threshold' => $this->convertThreshold($score, $convDef),
            'direct' => $this->convertDirect($score, $convDef),
            default => 0.0,
        };
    }

    /** @param array<string, mixed> $def */
    private function convertLinear(float $score, array $def): float
    {
        $minPct = (float) $def['min_pct'];
        $maxPct = (float) $def['max_pct'];
        $minGrade = (float) $def['min_grade'];
        $maxGrade = (float) $def['max_grade'];

        if ($score < $minPct) {
            return 0.0;
        }

        if ($score >= $maxPct) {
            return $maxGrade;
        }

        $ratio = ($score - $minPct) / ($maxPct - $minPct);

        return $minGrade + $ratio * ($maxGrade - $minGrade);
    }

    /** @param array<string, mixed> $def */
    private function convertThreshold(float $score, array $def): float
    {
        $steps = $def['steps'] ?? [];
        // Sort ascending by min_pct
        usort($steps, fn ($a, $b) => $a['min_pct'] <=> $b['min_pct']);

        $grade = 0.0;
        foreach ($steps as $step) {
            if ($score >= (float) $step['min_pct']) {
                $grade = (float) $step['grade'];
            }
        }

        return $grade;
    }

    /** @param array<string, mixed> $def */
    private function convertDirect(float $score, array $def): float
    {
        $maxGrade = (float) ($def['max_grade'] ?? 5.0);

        return round(($score / 100.0) * $maxGrade, 2);
    }

    /**
     * @param  array<string, mixed>  $componentResults
     * @param  array<int, mixed>  $gateFailures
     * @param  array<string, mixed>  $scheme
     */
    private function buildNumericResult(
        float $fgSum,
        bool $gatesPassed,
        array $componentResults,
        array $gateFailures,
        ?array $scheme,
    ): GradingResult {
        $rawFg = $fgSum;
        $fg = (int) round($rawFg);
        $fg = max(0, min(5, $fg));

        $passed = $gatesPassed && $fg >= 1;

        $finalGrade = $passed ? (string) $fg : '0';
        $gradePoints = (float) $fg;

        // Diagnostic percentage: map 0-5 to 0-100
        $finalPercentage = round($rawFg / 5.0 * 100, 2);

        return new GradingResult(
            finalPercentage: $finalPercentage,
            finalGrade: $finalGrade,
            gradePoints: $gradePoints,
            passed: $passed,
            gradeBreakdown: [
                'engine' => 'metropolia_v1',
                'scale' => $scheme['scale'] ?? '0-5',
                'components' => $componentResults,
                'fg_sum_raw' => $rawFg,
                'fg_rounded' => $fg,
                'gates_passed' => $gatesPassed,
                'gate_failures' => $gateFailures,
                'final_grade' => $finalGrade,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $componentResults
     * @param  array<int, mixed>  $gateFailures
     * @param  array<string, mixed>  $scheme
     */
    private function buildPassFailResult(
        bool $gatesPassed,
        array $componentResults,
        array $gateFailures,
        ?array $scheme,
    ): GradingResult {
        // For pass_fail scale: check pass_fail conversion components
        $passed = $gatesPassed;

        // If any component used pass_fail conversion, check its result
        foreach ($scheme['components'] ?? [] as $comp) {
            $convDef = $comp['conversion'] ?? null;
            if ($convDef !== null && ($convDef['type'] ?? '') === 'pass_fail') {
                $code = $comp['code'];
                $score = $componentResults[$code]['score_pct'] ?? null;
                if ($score !== null && $score < (float) $convDef['min_pct']) {
                    $passed = false;
                }
            }
        }

        return new GradingResult(
            finalPercentage: null,
            finalGrade: $passed ? 'P' : 'F',
            gradePoints: $passed ? 1.0 : 0.0,
            passed: $passed,
            gradeBreakdown: [
                'engine' => 'metropolia_v1',
                'scale' => 'pass_fail',
                'components' => $componentResults,
                'gates_passed' => $gatesPassed,
                'gate_failures' => $gateFailures,
                'final_grade' => $passed ? 'P' : 'F',
            ],
        );
    }
}
