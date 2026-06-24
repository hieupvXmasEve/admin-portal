<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support\Grading\Presenters;

use App\Models\AcademicRecord;

/**
 * Converts an AcademicRecord plus its stored `grade_breakdown` into a stable,
 * display-safe contract for the student and lecturer portals.
 *
 * Internal calculation fields produced by the grading calculators
 * (`fg_sum_raw`, `gates_passed`, `gate_failures`, …) are never exposed. The
 * output is backwards compatible: records without a custom scheme fall back to
 * `default_weighted_percentage` and the existing percentage fields.
 *
 * Output shape:
 * array{
 *   scheme_engine: string,
 *   scale: 'numeric_0_5'|'pass_fail'|'percentage',
 *   final_label: string,
 *   final_numeric: int|float|null,
 *   pass_status: 'passed'|'failed',
 *   components: array<int, array{
 *     code: string,
 *     label: string,
 *     raw_percentage: int|float|string|null,
 *     converted_grade: int|float|string|null,
 *     requirement_status: string|null,
 *   }>,
 * }
 */
final class GradeDisplayPresenter
{
    /**
     * Present the display contract for a hydrated AcademicRecord model.
     *
     * @return array<string, mixed>
     */
    public function present(AcademicRecord $record): array
    {
        return $this->fromParts(
            $this->normalizeBreakdown($record->grade_breakdown),
            $record->final_letter_grade,
            $record->final_percentage,
            (bool) $record->is_passed,
        );
    }

    /**
     * Build the display contract from raw parts.
     *
     * Used by the lecturer grade matrix, which loads academic rows through a
     * raw query: `grade_breakdown` arrives as a JSON string and `is_passed` as
     * an integer, so callers decode/cast before passing them in.
     *
     * @param  array<string, mixed>  $breakdown
     * @return array<string, mixed>
     */
    public function fromParts(
        array $breakdown,
        ?string $finalLetterGrade,
        int|float|string|null $finalPercentage,
        bool $isPassed,
    ): array {
        return [
            'scheme_engine' => (string) ($breakdown['engine'] ?? 'default_weighted_percentage'),
            'scale' => $this->normalizeScale($breakdown['scale'] ?? null),
            'final_label' => (string) ($breakdown['final_grade'] ?? $breakdown['final_label'] ?? $finalLetterGrade ?? ''),
            'final_numeric' => $this->finalNumeric($breakdown, $finalPercentage),
            'pass_status' => $isPassed ? 'passed' : 'failed',
            'components' => $this->components($breakdown['components'] ?? []),
        ];
    }

    /**
     * Normalize the raw breakdown attribute into an array. Eloquent casts it to
     * an array, but raw query rows deliver a JSON string.
     *
     * @return array<string, mixed>
     */
    private function normalizeBreakdown(mixed $breakdown): array
    {
        if (is_array($breakdown)) {
            return $breakdown;
        }

        if (is_string($breakdown) && $breakdown !== '') {
            $decoded = json_decode($breakdown, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Map the internal scale key to the public display scale. The calculators
     * store `0-5`; older/guessed payloads may already be normalized.
     */
    private function normalizeScale(mixed $scale): string
    {
        return match ((string) $scale) {
            '0-5', 'numeric_0_5' => 'numeric_0_5',
            'pass_fail' => 'pass_fail',
            default => 'percentage',
        };
    }

    /**
     * Resolve the numeric final value, preferring the rounded scheme grade and
     * falling back to the diagnostic percentage.
     *
     * @param  array<string, mixed>  $breakdown
     */
    private function finalNumeric(array $breakdown, int|float|string|null $finalPercentage): int|float|null
    {
        foreach (['fg_rounded', 'final_numeric'] as $key) {
            if (isset($breakdown[$key]) && is_numeric($breakdown[$key])) {
                $value = $breakdown[$key];

                return is_string($value) ? $value + 0 : $value;
            }
        }

        return $finalPercentage !== null ? (float) $finalPercentage : null;
    }

    /**
     * Transform the components map/list into a display-safe list. The numeric
     * calculator keys components by code with `{score_pct, converted_grade,
     * gate_met}`; the contract exposes a positional list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function components(mixed $components): array
    {
        if (! is_array($components)) {
            return [];
        }

        $result = [];

        foreach ($components as $key => $component) {
            if (! is_array($component)) {
                continue;
            }

            $code = (string) ($component['code'] ?? (is_string($key) ? $key : ''));

            $result[] = [
                'code' => $code,
                'label' => (string) ($component['label'] ?? $code),
                'raw_percentage' => $component['raw_percentage'] ?? $component['score_pct'] ?? null,
                'converted_grade' => $component['converted_grade'] ?? null,
                'requirement_status' => $this->requirementStatus($component),
            ];
        }

        return $result;
    }

    /**
     * Derive a display requirement status from an explicit value or the gate
     * outcome stored by the numeric calculator.
     *
     * @param  array<string, mixed>  $component
     */
    private function requirementStatus(array $component): ?string
    {
        if (isset($component['requirement_status']) && $component['requirement_status'] !== null) {
            return (string) $component['requirement_status'];
        }

        if (array_key_exists('gate_met', $component) && $component['gate_met'] !== null) {
            return $component['gate_met'] ? 'passed' : 'failed';
        }

        return null;
    }
}
