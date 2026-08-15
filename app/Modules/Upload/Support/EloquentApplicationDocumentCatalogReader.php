<?php

declare(strict_types=1);

namespace App\Modules\Upload\Support;

use App\Modules\Upload\Models\ApplicationDocumentType;
use App\Shared\Contracts\Upload\ApplicationDocumentCatalogReader;
use App\Shared\Contracts\Upload\DTO\ApplicationDocumentTypeSummary;

class EloquentApplicationDocumentCatalogReader implements ApplicationDocumentCatalogReader
{
    /** @return list<ApplicationDocumentTypeSummary> */
    public function activeOrdered(): array
    {
        return ApplicationDocumentType::query()
            ->activeOrdered()
            ->get()
            ->map(fn (ApplicationDocumentType $type) => new ApplicationDocumentTypeSummary(
                code: $type->code,
                name: $type->name,
                required: $type->required,
                intRequired: $type->int_required,
            ))
            ->all();
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, string>
     */
    public function namesByCode(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        return ApplicationDocumentType::query()
            ->whereIn('code', $codes)
            ->pluck('name', 'code')
            ->all();
    }
}
