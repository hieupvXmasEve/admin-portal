<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Facilities\DTO;

final readonly class SpaceReference
{
    public function __construct(
        public int $id,
        public int $campusId,
        public string $name,
        public string $code,
        public int $capacity,
        public ?string $type = null,
        public ?string $status = null,
        public bool $isBookable = true,
        /** @var array{id: int, name: string, code: string}|null */
        public ?array $building = null,
    ) {}

    /** @return array{id: int, campus_id: int, name: string, code: string, capacity: int} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'campus_id' => $this->campusId,
            'name' => $this->name,
            'code' => $this->code,
            'capacity' => $this->capacity,
        ];
    }
}
