<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Actions;

use App\Models\StudentApplication;

final class CreateApplicationAction
{
    /** @param array<string, mixed> $data */
    public static function run(array $data): StudentApplication
    {
        return StudentApplication::query()->create($data);
    }
}
