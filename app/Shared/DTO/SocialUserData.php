<?php

namespace App\Shared\DTO;

class SocialUserData
{
    public function __construct(
        public readonly string $email,
        public readonly string $name,
    ) {}
}
