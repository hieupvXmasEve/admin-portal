<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Upload;

use App\Shared\Contracts\Upload\DTO\ApplicationDocumentTypeSummary;

interface ApplicationDocumentCatalogReader
{
    /** @return list<ApplicationDocumentTypeSummary> */
    public function activeOrdered(): array;

    /**
     * @param  list<string>  $codes
     * @return array<string, string> code => name
     */
    public function namesByCode(array $codes): array;
}
