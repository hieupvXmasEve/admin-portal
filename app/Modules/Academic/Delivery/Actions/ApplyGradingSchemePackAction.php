<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\SyllabusTemplate;
use App\Modules\Academic\Delivery\Support\Grading\GradingSchemeValidator;
use App\Modules\Academic\Delivery\Support\Grading\MetropoliaSchemeCatalog;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Applies a grading scheme pack to local syllabus templates for the current
 * database only.
 *
 * The action plans every mapping first (resolve template, load scheme, validate)
 * and only writes when commit mode is requested and the entire plan is clean.
 * Dry-run never mutates a row, and any unresolved or ambiguous mapping aborts the
 * whole run so commit mode can never leave partial data behind.
 */
final class ApplyGradingSchemePackAction
{
    public function __construct(
        private readonly MetropoliaSchemeCatalog $catalog,
        private readonly GradingSchemeValidator $validator,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $mappings
     * @return array{total:int,updated:int,skipped:int,errors:array<int,string>}
     */
    public function execute(array $mappings, bool $commit): array
    {
        $errors = [];
        $skipped = 0;

        /** @var array<int, array{template: SyllabusTemplate, scheme: array<string, mixed>}> $plannedWrites */
        $plannedWrites = [];

        foreach ($mappings as $index => $mapping) {
            if (! is_array($mapping)) {
                $errors[] = "mapping {$index}: must be an object";

                continue;
            }

            $mappingErrors = $this->planMapping($index, $mapping, $plannedWrites, $skipped);
            $errors = [...$errors, ...$mappingErrors];
        }

        // A dirty plan must never write: report zero updates and leave rows intact.
        if ($errors !== []) {
            return [
                'total' => count($mappings),
                'updated' => 0,
                'skipped' => $skipped,
                'errors' => $errors,
            ];
        }

        if ($commit) {
            $this->applyWrites($plannedWrites);
        }

        return [
            'total' => count($mappings),
            'updated' => count($plannedWrites),
            'skipped' => $skipped,
            'errors' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $mapping
     * @param  array<int, array{template: SyllabusTemplate, scheme: array<string, mixed>}>  $plannedWrites
     * @return array<int, string>
     */
    private function planMapping(int $index, array $mapping, array &$plannedWrites, int &$skipped): array
    {
        $schemeKey = $mapping['scheme_key'] ?? null;

        if (! is_string($schemeKey) || $schemeKey === '') {
            return ["mapping {$index}: scheme_key is required"];
        }

        try {
            $scheme = $this->catalog->get($schemeKey);
        } catch (RuntimeException $exception) {
            return ["mapping {$index}: {$exception->getMessage()}"];
        }

        $schemeErrors = $this->validator->validate($scheme);

        if ($schemeErrors !== []) {
            return ["mapping {$index}: ".implode('; ', $schemeErrors)];
        }

        try {
            $template = $this->findTemplate($mapping);
        } catch (RuntimeException $exception) {
            return ["mapping {$index}: {$exception->getMessage()}"];
        }

        if ($template->grading_scheme === $scheme) {
            $skipped++;

            return [];
        }

        $plannedWrites[] = ['template' => $template, 'scheme' => $scheme];

        return [];
    }

    /**
     * @param  array<int, array{template: SyllabusTemplate, scheme: array<string, mixed>}>  $plannedWrites
     */
    private function applyWrites(array $plannedWrites): void
    {
        try {
            DB::transaction(function () use ($plannedWrites): void {
                foreach ($plannedWrites as $write) {
                    $write['template']->forceFill(['grading_scheme' => $write['scheme']])->save();
                }
            });
        } catch (Throwable $exception) {
            throw new RuntimeException('Failed to apply grading scheme pack: '.$exception->getMessage(), previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function findTemplate(array $mapping): SyllabusTemplate
    {
        $unitCode = $mapping['unit_code'] ?? null;

        $query = SyllabusTemplate::query();

        if (is_string($unitCode) && $unitCode !== '') {
            $query->whereHas('unit', fn ($unitQuery) => $unitQuery->where('code', $unitCode));
        }

        if (! empty($mapping['syllabus_template_id'])) {
            $query->whereKey($mapping['syllabus_template_id']);
        }

        if (! empty($mapping['syllabus_title'])) {
            $query->where('title', $mapping['syllabus_title']);
        }

        if (! empty($mapping['campus_code'])) {
            $query->whereHas('applicableCampus', fn ($campusQuery) => $campusQuery->where('code', $mapping['campus_code']));
        }

        $matches = $query->get();

        if ($matches->count() !== 1) {
            throw new RuntimeException(
                "mapping for scheme [{$mapping['scheme_key']}] must resolve to exactly one syllabus template, found {$matches->count()}"
            );
        }

        return $matches->first();
    }
}
