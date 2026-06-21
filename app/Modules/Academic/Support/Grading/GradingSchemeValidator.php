<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support\Grading;

/**
 * Validates a single grading scheme against the metropolia_v1 contract before it
 * is stored on a syllabus template. The vocabulary mirrors what
 * {@see MetropoliaV1Calculator} can actually execute, so a scheme that passes
 * validation is guaranteed to be runnable by the engine.
 *
 * Returns a flat list of human-readable error strings. An empty array means the
 * scheme is valid.
 */
final class GradingSchemeValidator
{
    private const REQUIRED_KEYS = ['engine', 'version', 'scale', 'components'];

    private const VALID_SCALES = ['0-5', 'pass_fail'];

    private const VALID_CONVERSION_TYPES = ['linear', 'threshold', 'direct', 'pass_fail'];

    /**
     * @param  array<string, mixed>  $scheme
     * @return array<int, string>
     */
    public function validate(array $scheme): array
    {
        $errors = [];

        foreach (self::REQUIRED_KEYS as $key) {
            if (! array_key_exists($key, $scheme)) {
                $errors[] = "{$key} is required";
            }
        }

        if (($scheme['engine'] ?? null) !== 'metropolia_v1') {
            $errors[] = 'engine must be metropolia_v1';
        }

        if (! in_array($scheme['scale'] ?? null, self::VALID_SCALES, true)) {
            $errors[] = 'scale must be one of '.implode(', ', self::VALID_SCALES);
        }

        $components = $scheme['components'] ?? null;

        if (! is_array($components) || $components === []) {
            $errors[] = 'components must contain at least one component';

            return array_values(array_unique($errors));
        }

        foreach ($components as $index => $component) {
            $errors = [...$errors, ...$this->validateComponent($index, $component)];
        }

        return array_values(array_unique($errors));
    }

    /**
     * @return array<int, string>
     */
    private function validateComponent(int|string $index, mixed $component): array
    {
        if (! is_array($component)) {
            return ["components.{$index} must be an object"];
        }

        $errors = [];

        if (! array_key_exists('code', $component) || ! is_string($component['code']) || $component['code'] === '') {
            $errors[] = "components.{$index}.code is required";
        }

        $hasGate = array_key_exists('gate', $component);
        $hasConversion = array_key_exists('conversion', $component);

        if (! $hasGate && ! $hasConversion) {
            $errors[] = "components.{$index} must define a gate, a conversion, or both";
        }

        if ($hasGate) {
            $errors = [...$errors, ...$this->validateGate($index, $component['gate'])];
        }

        if ($hasConversion) {
            $errors = [...$errors, ...$this->validateConversion($index, $component['conversion'])];
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    private function validateGate(int|string $index, mixed $gate): array
    {
        if (! is_array($gate) || ! $this->isNumeric($gate['min_pct'] ?? null)) {
            return ["components.{$index}.gate.min_pct must be numeric"];
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function validateConversion(int|string $index, mixed $conversion): array
    {
        if (! is_array($conversion)) {
            return ["components.{$index}.conversion must be an object"];
        }

        $type = $conversion['type'] ?? null;

        if (! in_array($type, self::VALID_CONVERSION_TYPES, true)) {
            return ["components.{$index}.conversion.type must be one of ".implode(', ', self::VALID_CONVERSION_TYPES)];
        }

        return match ($type) {
            'linear' => $this->requireNumericKeys($index, $conversion, ['min_pct', 'max_pct', 'min_grade', 'max_grade']),
            'direct' => $this->requireNumericKeys($index, $conversion, ['max_grade']),
            'pass_fail' => $this->requireNumericKeys($index, $conversion, ['min_pct']),
            'threshold' => $this->validateThresholdSteps($index, $conversion),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $conversion
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    private function requireNumericKeys(int|string $index, array $conversion, array $keys): array
    {
        $errors = [];

        foreach ($keys as $key) {
            if (! $this->isNumeric($conversion[$key] ?? null)) {
                $errors[] = "components.{$index}.conversion.{$key} must be numeric";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $conversion
     * @return array<int, string>
     */
    private function validateThresholdSteps(int|string $index, array $conversion): array
    {
        $steps = $conversion['steps'] ?? null;

        if (! is_array($steps) || $steps === []) {
            return ["components.{$index}.conversion.steps must contain at least one step"];
        }

        $errors = [];

        foreach ($steps as $stepIndex => $step) {
            if (! is_array($step) || ! $this->isNumeric($step['min_pct'] ?? null) || ! $this->isNumeric($step['grade'] ?? null)) {
                $errors[] = "components.{$index}.conversion.steps.{$stepIndex} must define numeric min_pct and grade";
            }
        }

        return $errors;
    }

    private function isNumeric(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }
}
