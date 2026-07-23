<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Support;

use App\Shared\Contracts\Academic\AdmissionsIntentReader;
use App\Shared\Contracts\Admissions\ApplicationProgramMappingReader;

final class EloquentApplicationProgramMappingReader implements ApplicationProgramMappingReader
{
    public function __construct(private readonly AdmissionsIntentReader $academicIntentReader) {}

    public function resolve(array $applicationData): array
    {
        return $this->academicIntentReader->resolveAdmissionsIntent($applicationData);
    }

    public function formOptions(): array
    {
        return $this->academicIntentReader->admissionsFormOptions();
    }
}
