<?php

declare(strict_types=1);

namespace App\Shared\Contracts\StudentRegistry\DTO;

final readonly class StudentPortalToken
{
    public function __construct(
        public string $token,
        public string $tokenType,
        public string $expiresAt,
    ) {}

    /** @return array{token:string, token_type:string, expires_at:string} */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'token_type' => $this->tokenType,
            'expires_at' => $this->expiresAt,
        ];
    }
}
