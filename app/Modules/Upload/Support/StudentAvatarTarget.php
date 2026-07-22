<?php

declare(strict_types=1);

namespace App\Modules\Upload\Support;

final readonly class StudentAvatarTarget
{
    public function __construct(public int $studentId) {}
}
