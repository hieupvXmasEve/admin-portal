<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Support;

use App\Services\ProgramMappingService;
use App\Shared\Contracts\Admissions\ApplicationProgramMappingReader;

final class EloquentApplicationProgramMappingReader implements ApplicationProgramMappingReader
{
    public function __construct(private readonly ProgramMappingService $programMappingService) {}

    public function resolve(array $applicationData): array
    {
        return $this->programMappingService->resolveApplicationMappingData($applicationData);
    }
}
