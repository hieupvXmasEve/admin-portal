<?php

declare(strict_types=1);

namespace App\Http\Requests\SyllabusTemplate\Concerns;

use App\Modules\Academic\Support\Grading\GradingSchemeValidator;
use Illuminate\Validation\Validator;

/**
 * Shared grading-scheme validation for the syllabus template store/update
 * requests. Runs three checks only when a custom scheme is submitted (a
 * non-empty `grading_scheme` array); the default weighted path
 * (`grading_scheme = null`) is left untouched.
 *
 * 1. Engine contract — reuses {@see GradingSchemeValidator}, the same validator
 *    runtime grading uses, so an invalid scheme is rejected at save instead of
 *    failing silently at finalization.
 * 2. Duplicate codes — assessment component codes must be unique within the
 *    submission (they are the join key the calculators read).
 * 3. Subset rule — every code referenced by the scheme (`components[].code` and
 *    `pass_requirements[].code`) must be a declared assessment component code,
 *    otherwise the formula/conversion would read 0 for that variable at
 *    grading time.
 */
trait ValidatesGradingScheme
{
    protected function validateGradingScheme(Validator $validator): void
    {
        $scheme = $this->input('grading_scheme');

        if (! is_array($scheme) || $scheme === []) {
            return; // default weighted path — nothing to enforce
        }

        foreach (app(GradingSchemeValidator::class)->validate($scheme) as $error) {
            $validator->errors()->add('grading_scheme', $error);
        }

        $components = $this->input('assessment_components');

        if (! is_array($components)) {
            return;
        }

        $declaredCodes = $this->collectDeclaredCodes($validator, $components);

        foreach ($this->schemeReferencedCodes($scheme) as $referenced) {
            if (! in_array($referenced, $declaredCodes, true)) {
                $validator->errors()->add(
                    'grading_scheme',
                    "Grading scheme references component code [{$referenced}] that is not a declared assessment component.",
                );
            }
        }
    }

    /**
     * Collect unique assessment component codes, flagging duplicates inline.
     *
     * @param  array<int, mixed>  $components
     * @return array<int, string>
     */
    private function collectDeclaredCodes(Validator $validator, array $components): array
    {
        $declaredCodes = [];
        $seen = [];

        foreach ($components as $index => $component) {
            $code = is_array($component) ? ($component['code'] ?? null) : null;

            if (! is_string($code) || $code === '') {
                continue; // the required_with rule reports the missing code
            }

            if (isset($seen[$code])) {
                $validator->errors()->add(
                    "assessment_components.{$index}.code",
                    "Assessment component code [{$code}] is duplicated.",
                );

                continue;
            }

            $seen[$code] = true;
            $declaredCodes[] = $code;
        }

        return $declaredCodes;
    }

    /**
     * @param  array<string, mixed>  $scheme
     * @return array<int, string>
     */
    private function schemeReferencedCodes(array $scheme): array
    {
        $codes = [];

        foreach ($scheme['components'] ?? [] as $component) {
            if (is_array($component) && isset($component['code']) && is_string($component['code'])) {
                $codes[] = $component['code'];
            }
        }

        foreach ($scheme['pass_requirements'] ?? [] as $requirement) {
            if (is_array($requirement) && isset($requirement['code']) && is_string($requirement['code'])) {
                $codes[] = $requirement['code'];
            }
        }

        return array_values(array_unique($codes));
    }
}
