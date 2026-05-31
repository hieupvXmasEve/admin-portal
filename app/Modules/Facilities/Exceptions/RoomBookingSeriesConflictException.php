<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Exceptions;

use RuntimeException;

class RoomBookingSeriesConflictException extends RuntimeException
{
    public function __construct(
        private readonly array $occurrences,
        string $message = 'One or more booking occurrences are unavailable.'
    ) {
        parent::__construct($message);
    }

    public function occurrences(): array
    {
        return $this->occurrences;
    }

    public function conflicts(): array
    {
        return collect($this->occurrences)
            ->flatMap(fn (array $occurrence) => $occurrence['conflicts'] ?? [])
            ->values()
            ->all();
    }
}
