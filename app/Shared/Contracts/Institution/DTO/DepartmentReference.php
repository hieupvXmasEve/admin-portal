<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Institution\DTO;

final readonly class DepartmentReference
{
    public function __construct(
        public int $id,
        public string $name,
        public string $code,
    ) {}

    /**
     * @return array{id: int, name: string, code: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
        ];
    }
}
