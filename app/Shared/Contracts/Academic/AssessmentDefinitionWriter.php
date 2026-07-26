<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

interface AssessmentDefinitionWriter
{
    /** @return array<string, string> */
    public function types(): array;

    /** @param list<array<string, mixed>> $components */
    public function syncForSyllabusTemplate(int $syllabusTemplateId, array $components): void;

    /**
     * @param  list<array<string, mixed>>  $components
     * @param  list<array<string, mixed>>  $details
     * @return array{assessment_components: array{created: int, skipped: int}, assessment_details: array{created: int, skipped: int}, errors: list<string>, warnings: list<string>}
     */
    public function importForSyllabusTemplates(array $components, array $details): array;
}
