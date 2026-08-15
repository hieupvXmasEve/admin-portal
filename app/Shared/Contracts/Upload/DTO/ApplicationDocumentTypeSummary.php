<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Upload\DTO;

final readonly class ApplicationDocumentTypeSummary
{
    public function __construct(
        public string $code,
        public string $name,
        public bool $required,
        public bool $intRequired,
    ) {}
}
