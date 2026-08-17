<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Support;

use App\Models\Semester;
use App\Modules\Admissions\Support\Crm\CrmMappingSettings;
use App\Shared\Contracts\Admissions\IntakeSemesterReader;

final class EloquentIntakeSemesterReader implements IntakeSemesterReader
{
    public function __construct(private readonly CrmMappingSettings $settings) {}

    public function currentIntakeSemesterId(): ?int
    {
        $code = $this->settings->getIntakeCode();

        if ($code === null) {
            return null;
        }

        return Semester::query()->where('code', $code)->value('id');
    }
}
