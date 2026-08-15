<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Queries;

use App\Models\StudentApplication;
use App\Shared\Contracts\Upload\ApplicationDocumentCatalogReader;
use App\Shared\Contracts\Upload\DTO\ApplicationDocumentTypeSummary;
use Illuminate\Support\Collection;

final class GetApplicantDocumentChecklistQuery
{
    public function __construct(private readonly ApplicationDocumentCatalogReader $documentCatalog) {}

    /**
     * @return array{groups: list<array{code: string, name: string, required: bool, is_missing: bool, documents: array<int, array<string, mixed>>}>, missing_required: list<string>}
     */
    public function handle(StudentApplication $application): array
    {
        $documents = $application->relationLoaded('documents')
            ? $application->documents
            : $application->documents()->orderBy('file_type_code')->orderBy('page_index')->orderBy('id')->get();
        $byCode = $documents->groupBy('file_type_code');
        $types = $this->documentCatalog->activeOrdered();
        $catalogued = collect($types)->map(function (ApplicationDocumentTypeSummary $type) use ($application, $byCode): array {
            $documentsForType = $byCode->get($type->code, collect());
            $required = $type->required || ($application->is_international_applicant && $type->intRequired);

            return [
                'code' => $type->code,
                'name' => $type->name,
                'required' => $required,
                'is_missing' => $required && $documentsForType->isEmpty(),
                'documents' => $this->present($documentsForType),
            ];
        });
        $uncatalogued = $byCode->keys()->diff(collect($types)->pluck('code'))->map(function (string $code) use ($byCode): array {
            $documentsForType = $byCode->get($code);

            return [
                'code' => $code,
                'name' => $documentsForType->first()->file_type_name ?? $code,
                'required' => false,
                'is_missing' => false,
                'documents' => $this->present($documentsForType),
            ];
        });
        $groups = $catalogued->concat($uncatalogued)->values();

        return [
            'groups' => $groups->all(),
            'missing_required' => $groups->where('is_missing', true)->pluck('code')->values()->all(),
        ];
    }

    /**
     * $documents is a Collection of Upload's ApplicationDocument rows.
     * Named in prose, not `Collection<int, ApplicationDocument>`: Pint's
     * fully_qualified_strict_types fixer would import the fully qualified
     * name and trip the zero-tolerance cross_context_concrete_imports
     * boundary rule.
     *
     * @return array<int, array<string, mixed>>
     */
    private function present(Collection $documents): array
    {
        return $documents->sortBy([['page_index', 'asc'], ['id', 'asc']])->map(
            fn ($document): array => [
                'id' => $document->id,
                'page_index' => $document->page_index,
                'original_name' => $document->original_name,
                'link' => $document->link,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
                'status' => $document->status,
            ],
        )->values()->all();
    }
}
