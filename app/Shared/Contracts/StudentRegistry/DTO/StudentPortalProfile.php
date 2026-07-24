<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry\DTO;

final readonly class StudentPortalProfile
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private array $payload) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }
}
