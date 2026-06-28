<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves an Application's admission-intent codes to the concrete academic
 * records a Student is created under on Approve (ADR-0005).
 *
 * Inputs are canonical codes — `Campus.code`, `Program.code`, `Semester.code`
 * (intake) — validated to exist at the write boundary. The Curriculum Version is
 * derived from `(program, semester [, specialization])` and must match **exactly
 * one** row: the returned `curriculum_match_count` lets the approve guard fail
 * closed on 0 or many instead of silently picking one. There is no name→code
 * translation; the code IS the key.
 */
class ProgramMappingService
{
    private const CACHE_TTL_SECONDS = 600;

    /**
     * Resolve the campus / program / intake-semester / curriculum ids from an
     * Application's codes.
     *
     * @param  array{campus_code?: ?string, intended_program?: ?string, intake?: ?string, intended_specialization?: ?string}  $applicationData
     * @return array{campus_id: ?int, program_id: ?int, intake_semester_id: ?int, curriculum_version_id: ?int, curriculum_match_count: int, specialization_id: ?int}
     */
    public function resolveApplicationMappingData(array $applicationData): array
    {
        $campusId = $this->idFromCode(Campus::class, 'campus_by_code', $applicationData['campus_code'] ?? null);
        $programId = $this->idFromCode(Program::class, 'program_by_code', $applicationData['intended_program'] ?? null);
        $semesterId = $this->idFromCode(Semester::class, 'semester_by_code', $applicationData['intake'] ?? null);
        $specializationId = $this->specializationIdFromCode($applicationData['intended_specialization'] ?? null, $programId);

        [$curriculumVersionId, $curriculumMatchCount, $resolvedSpecializationId] =
            $this->resolveCurriculum($programId, $semesterId, $specializationId);

        return [
            'campus_id' => $campusId,
            'program_id' => $programId,
            // The intake IS the starting Semester (ADR-0005).
            'intake_semester_id' => $semesterId,
            'curriculum_version_id' => $curriculumVersionId,
            'curriculum_match_count' => $curriculumMatchCount,
            // Specialization is taken from the resolved Curriculum Version, the
            // single source of truth, not from the (deferred) intent field.
            'specialization_id' => $resolvedSpecializationId,
        ];
    }

    /**
     * Resolve the Curriculum Version for a program + semester, optionally narrowed
     * by specialization. Returns `[id, matchCount, specializationId]`; the id and
     * specialization are only set when exactly one version matches.
     *
     * @return array{0: ?int, 1: int, 2: ?int}
     */
    private function resolveCurriculum(?int $programId, ?int $semesterId, ?int $specializationId): array
    {
        if ($programId === null || $semesterId === null) {
            return [null, 0, null];
        }

        $versions = CurriculumVersion::query()
            ->where('program_id', $programId)
            ->where('semester_id', $semesterId)
            ->when($specializationId !== null, fn ($query) => $query->where('specialization_id', $specializationId))
            ->get(['id', 'specialization_id']);

        if ($versions->count() === 1) {
            $version = $versions->first();

            return [$version->id, 1, $version->specialization_id];
        }

        return [null, $versions->count(), null];
    }

    /**
     * Resolve a specialization id from its code (scoped to the program when known).
     */
    private function specializationIdFromCode(?string $code, ?int $programId): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }

        return Specialization::query()
            ->where('code', $code)
            ->when($programId !== null, fn ($query) => $query->where('program_id', $programId))
            ->value('id');
    }

    /**
     * Look up a row id by its `code`, cached briefly. Codes → ids are stable, so a
     * short TTL keeps the hot approve/ingest paths cheap.
     *
     * @param  class-string<Model>  $model
     */
    private function idFromCode(string $model, string $cachePrefix, ?string $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }

        $id = Cache::remember(
            "{$cachePrefix}_{$code}",
            self::CACHE_TTL_SECONDS,
            fn () => $model::query()->where('code', $code)->value('id'),
        );

        return $id !== null ? (int) $id : null;
    }
}
