<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Identity\DTO;

final readonly class UserDirectoryEntry
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}

    /** @return array{id:int, name:string, email:string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
