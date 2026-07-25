<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic\DTO;

final readonly class AcademicFinanceObligationSource
{
    public function __construct(
        public string $source_system,
        public string $source_kind,
        public string $source_ref,
        public string $obligation_type,
    ) {}
}
