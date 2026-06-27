<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentType;
use App\Models\StudentApplication;
use Illuminate\Support\Collection;

/**
 * Builds the staff-facing document checklist for an Application: the catalog's
 * document types with the Application's documents grouped under each, plus which
 * required types have no document yet ("missing required documents", ADR-0004).
 *
 * Required-ness honours `int_required` for international applicants
 * ({@see ApplicationDocumentType::isRequiredFor()}). Documents whose type is not
 * in the active catalog (legacy or unknown CRM codes) are still listed under
 * their own code so nothing is hidden, but never counted as required/missing.
 */
class ApplicationDocumentService
{
    /**
     * @return array{
     *     groups: list<array{code: string, name: string, required: bool, is_missing: bool, documents: array<int, array<string, mixed>>}>,
     *     missing_required: list<string>
     * }
     */
    public function checklist(StudentApplication $application): array
    {
        $documents = $application->relationLoaded('documents')
            ? $application->documents
            : $application->documents()->orderBy('file_type_code')->orderBy('page_index')->orderBy('id')->get();

        $byCode = $documents->groupBy('file_type_code');
        $types = ApplicationDocumentType::query()->activeOrdered()->get();

        $catalogued = $types->map(function (ApplicationDocumentType $type) use ($application, $byCode): array {
            $docs = $byCode->get($type->code, collect());
            $required = $type->isRequiredFor($application);

            return [
                'code' => $type->code,
                'name' => $type->name,
                'required' => $required,
                'is_missing' => $required && $docs->isEmpty(),
                'documents' => $this->presentDocuments($docs),
            ];
        });

        $uncatalogued = $byCode->keys()
            ->diff($types->pluck('code'))
            ->map(function (string $code) use ($byCode): array {
                $docs = $byCode->get($code);

                return [
                    'code' => $code,
                    'name' => $docs->first()->file_type_name ?? $code,
                    'required' => false,
                    'is_missing' => false,
                    'documents' => $this->presentDocuments($docs),
                ];
            });

        $groups = $catalogued->concat($uncatalogued)->values();

        return [
            'groups' => $groups->all(),
            'missing_required' => $groups->where('is_missing', true)->pluck('code')->values()->all(),
        ];
    }

    /**
     * Shape the documents for display, ordered by page index then id.
     *
     * @param  Collection<int, ApplicationDocument>  $documents
     * @return array<int, array<string, mixed>>
     */
    private function presentDocuments(Collection $documents): array
    {
        return $documents
            ->sortBy([['page_index', 'asc'], ['id', 'asc']])
            ->map(fn (ApplicationDocument $document) => [
                'id' => $document->id,
                'page_index' => $document->page_index,
                'original_name' => $document->original_name,
                'link' => $document->link,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
                'status' => $document->status,
            ])
            ->values()
            ->all();
    }
}
