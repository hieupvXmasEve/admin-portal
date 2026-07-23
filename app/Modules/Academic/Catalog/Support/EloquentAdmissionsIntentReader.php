<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Support;

use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Specialization;
use App\Shared\Contracts\Academic\AdmissionsIntentReader;
use App\Shared\Contracts\Institution\CampusReferenceReader;

final class EloquentAdmissionsIntentReader implements AdmissionsIntentReader
{
    public function __construct(private readonly CampusReferenceReader $campuses) {}

    public function resolveAdmissionsIntent(array $intent): array
    {
        $campusId = collect($this->campuses->all())->first(fn ($campus) => $campus->code === ($intent['campus_code'] ?? null))?->id;
        $programId = Program::query()->where('code', $intent['intended_program'] ?? null)->value('id');
        $semesterId = Semester::query()->where('code', $intent['intake'] ?? null)->value('id');
        $specializationId = Specialization::query()->where('code', $intent['intended_specialization'] ?? null)->when($programId !== null, fn ($query) => $query->where('program_id', $programId))->value('id');
        $versions = ($programId === null || $semesterId === null) ? collect() : CurriculumVersion::query()->where('program_id', $programId)->where('semester_id', $semesterId)->when($specializationId !== null, fn ($query) => $query->where('specialization_id', $specializationId))->get(['id', 'specialization_id']);
        $version = $versions->count() === 1 ? $versions->first() : null;

        return ['campus_id' => $campusId, 'program_id' => $programId, 'intake_semester_id' => $semesterId, 'curriculum_version_id' => $version?->id, 'curriculum_match_count' => $versions->count(), 'specialization_id' => $version?->specialization_id];
    }

    public function admissionsFormOptions(): array
    {
        return ['campuses' => collect($this->campuses->all())->map(fn ($item): array => ['id' => $item->id, 'code' => $item->code, 'name' => $item->name])->all(), 'programs' => Program::query()->orderBy('name')->get(['id', 'code', 'name'])->map(fn (Program $item): array => ['id' => $item->id, 'code' => $item->code, 'name' => $item->name])->all(), 'semesters' => Semester::query()->orderBy('code')->get(['id', 'code', 'name'])->map(fn (Semester $item): array => ['id' => $item->id, 'code' => $item->code, 'name' => $item->name])->all()];
    }
}
