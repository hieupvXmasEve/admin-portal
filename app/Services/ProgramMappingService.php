<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProgramMappingService
{
    /**
     * Mapping between intended_program codes and actual program codes
     */
    private const PROGRAM_CODE_MAPPING = [
        'CS' => 'SEMI',           // Computer Science → Software Engineering and Mobile Intelligence
        'Trí tuệ nhân tạo' => 'AI',  // AI Vietnamese name → AI code
        'AI' => 'AI',             // AI → AI (direct match)
        'TC' => 'FIN',            // TC → Finance
        'IT' => 'BA',             // IT → Business Analytics
    ];

    /**
     * Map intended program code to actual program code
     */
    public function mapIntendedProgramToCode(string $intendedProgram): ?string
    {
        return self::PROGRAM_CODE_MAPPING[$intendedProgram] ?? null;
    }

    /**
     * Get program ID from intended program code
     */
    public function getProgramIdFromIntendedCode(string $intendedProgram): ?int
    {
        $programCode = $this->mapIntendedProgramToCode($intendedProgram);

        if (!$programCode) {
            return null;
        }

        $program = Cache::remember(
            "program_by_code_{$programCode}",
            10, // 1 hour
            fn() => Program::where('code', $programCode)->first()
        );

        return $program?->id;
    }

    /**
     * Get campus ID from campus code
     */
    public function getCampusIdFromCode(string $campusCode): ?int
    {
        $campus = Cache::remember(
            "campus_by_code_{$campusCode}",
            10, // 1 hour
            fn() => Campus::where('code', $campusCode)->first()
        );

        return $campus?->id;
    }

    /**
     * Get curriculum version ID from intake code and program ID
     */
    public function getCurriculumVersionId(string $intakeCode, int $programId): ?int
    {
        // First try to find by version_code matching intake
        $curriculumVersion = Cache::remember(
            "curriculum_version_{$intakeCode}_{$programId}",
            10, // 30 minutes
            function () use ($intakeCode, $programId) {
                return CurriculumVersion::where('version_code', $intakeCode)
                    ->where('program_id', $programId)
                    ->first();
            }
        );

        if ($curriculumVersion) {
            return $curriculumVersion->id;
        }

        // If not found by version_code, try to find by semester code
        $semester = Cache::remember(
            "semester_by_code_{$intakeCode}",
            10, // 30 minutes
            fn() => Semester::where('code', $intakeCode)->first()
        );

        if (!$semester) {
            return null;
        }

        // Find curriculum version by semester and program
        $curriculumVersion = Cache::remember(
            "curriculum_version_semester_{$semester->id}_{$programId}",
            10, // 30 minutes
            function () use ($semester, $programId) {
                return CurriculumVersion::where('semester_id', $semester->id)
                    ->where('program_id', $programId)
                    ->first();
            }
        );

        return $curriculumVersion?->id;
    }

    /**
     * Resolve all mapping data for a student application
     */
    public function resolveApplicationMappingData(array $applicationData): array
    {
        Log::info('ProgramMappingService: Starting mapping resolution', [
            'input_data' => $applicationData
        ]);
        
        $resolvedData = [];

        // Resolve campus_id
        if (!empty($applicationData['campus_code'])) {
            Log::info("Resolving campus ID for code: {$applicationData['campus_code']}");
            $campusId = $this->getCampusIdFromCode($applicationData['campus_code']);
            $resolvedData['campus_id'] = $campusId;
            Log::info("Campus ID resolved: " . ($campusId ? $campusId : 'NULL'));
        } else {
            Log::warning('Campus code is empty, cannot resolve campus_id');
        }

        // Resolve program_id
        if (!empty($applicationData['intended_program'])) {
            Log::info("Resolving program ID for intended program: {$applicationData['intended_program']}");
            $mappedCode = $this->mapIntendedProgramToCode($applicationData['intended_program']);
            Log::info("Mapped program code: " . ($mappedCode ? $mappedCode : 'NULL'));
            
            $programId = $this->getProgramIdFromIntendedCode($applicationData['intended_program']);
            $resolvedData['program_id'] = $programId;
            Log::info("Program ID resolved: " . ($programId ? $programId : 'NULL'));
        } else {
            Log::warning('Intended program is empty, cannot resolve program_id');
        }

        // Resolve curriculum_version_id
        if (!empty($applicationData['intake']) && !empty($resolvedData['program_id'])) {
            Log::info("Resolving curriculum version ID for intake: {$applicationData['intake']}, program_id: {$resolvedData['program_id']}");
            $curriculumVersionId = $this->getCurriculumVersionId(
                $applicationData['intake'],
                $resolvedData['program_id']
            );
            $resolvedData['curriculum_version_id'] = $curriculumVersionId;
            Log::info("Curriculum version ID resolved: " . ($curriculumVersionId ? $curriculumVersionId : 'NULL'));
        } else {
            if (empty($applicationData['intake'])) {
                Log::warning('Intake is empty, cannot resolve curriculum_version_id');
            }
            if (empty($resolvedData['program_id'])) {
                Log::warning('Program ID not resolved, cannot resolve curriculum_version_id');
            }
        }

        Log::info('ProgramMappingService: Mapping resolution complete', [
            'resolved_data' => $resolvedData
        ]);

        return $resolvedData;
    }

    /**
     * Get all program mappings for reference
     */
    public function getAllProgramMappings(): array
    {
        return self::PROGRAM_CODE_MAPPING;
    }

    /**
     * Validate mapping data completeness
     */
    public function validateMappingData(array $mappingData): array
    {
        $errors = [];

        if (empty($mappingData['campus_id'])) {
            $errors[] = 'Campus ID could not be resolved';
        }

        if (empty($mappingData['program_id'])) {
            $errors[] = 'Program ID could not be resolved';
        }

        if (empty($mappingData['curriculum_version_id'])) {
            $errors[] = 'Curriculum Version ID could not be resolved';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Clear mapping cache
     */
    public function clearMappingCache(): void
    {
        $patterns = [
            'program_by_code_*',
            'campus_by_code_*',
            'curriculum_version_*',
            'semester_by_code_*'
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}
