<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Engagement\DTO;

final readonly class FormPortalGate
{
    /**
     * @param  list<MandatoryFormAssignment>  $mandatoryAssignments
     */
    public function __construct(
        public bool $blocked,
        public array $mandatoryAssignments,
    ) {}
}
