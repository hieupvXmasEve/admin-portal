<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Exceptions;

use DomainException;

final class InvalidProgressionState extends DomainException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
