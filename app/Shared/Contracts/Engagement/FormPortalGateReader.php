<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Engagement;

use App\Shared\Contracts\Engagement\DTO\FormPortalGate;

interface FormPortalGateReader
{
    public function forStudentId(int $studentId): FormPortalGate;
}
