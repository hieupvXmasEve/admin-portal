<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Queries;

use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentType;
use App\Models\StudentApplication;
use Illuminate\Support\Collection;

final class GetApplicantDocumentChecklistQuery
{
    /**
     * @return array{groups: list<array{code: string, name: string, required: bool, is_missing: bool, documents: array<int, array<string, mixed>>}>, missing_required: list<string>}
     */
    public function handle(StudentApplication $application): array
    {
        $documents = $application->relationLoaded('documents')
            ? $application->documents
            : $application->documents()->orderBy('file_type_code')->orderBy('page_index')->orderBy('id')->get();
        $byCode = $documents->groupBy('file_type_code');
        $types = ApplicationDocumentType::query()->activeOrdered()->get();
        $catalogued = $types->map(function (ApplicationDocumentType $type) use ($application, $byCode): array {
            $documentsForType = $byCode->get($type->code, collect());
            $required = $type->isRequiredFor($application);

            return [
                'code' => $type->code,
                'name' => $type->name,
                'required' => $required,
                'is_missing' => $required && $documentsForType->isEmpty(),
                'documents' => $this->present($documentsForType),
            ];
        });
        $uncatalogued = $byCode->keys()->diff($types->pluck('code'))->map(function (string $code) use ($byCode): array {
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
     * @param  Collection<int, ApplicationDocument>  $documents
     * @return array<int, array<string, mixed>>
     */
    private function present(Collection $documents): array
    {
        return $documents->sortBy([['page_index', 'asc'], ['id', 'asc']])->map(
            fn (ApplicationDocument $document): array => [
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
