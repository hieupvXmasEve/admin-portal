<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Support;

use App\Models\CurriculumUnit;
use App\Models\Semester;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use App\Shared\Contracts\Academic\DTO\AcademicPeriodReference;
use App\Shared\Contracts\Academic\DTO\CourseOfferingSyllabusTemplateReference;
use App\Shared\Contracts\Academic\DTO\CourseOfferingUnitReference;
use Carbon\CarbonImmutable;

class EloquentCourseOfferingCatalogReader implements CourseOfferingCatalogReader
{
    public function currentOfferingPeriod(): ?AcademicPeriodReference
    {
        $period = Semester::query()->where('is_active', true)->first();

        return $this->mapPeriod($period);
    }

    public function offeringPeriod(int $academicPeriodId): ?AcademicPeriodReference
    {
        return $this->mapPeriod(Semester::query()->find($academicPeriodId));
    }

    private function mapPeriod(?Semester $period): ?AcademicPeriodReference
    {
        if ($period === null) {
            return null;
        }

        return new AcademicPeriodReference(
            id: (int) $period->id,
            code: (string) $period->code,
            name: (string) $period->name,
            start_date: $this->nullableDate($period->start_date),
            end_date: $this->nullableDate($period->end_date),
            registration_start_date: $this->nullableDate($period->enrollment_start_date),
            registration_end_date: $this->nullableDate($period->enrollment_end_date),
            is_current: (bool) $period->is_active,
        );
    }

    /**
     * @return list<CourseOfferingUnitReference>
     */
    public function offeringUnits(): array
    {
        return Unit::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'credit_points', 'level', 'unit_type'])
            ->map(fn (Unit $unit): CourseOfferingUnitReference => new CourseOfferingUnitReference(
                id: (int) $unit->id,
                code: (string) $unit->code,
                name: (string) $unit->name,
                credit_points: (string) $unit->credit_points,
                level: $unit->level === null ? null : (int) $unit->level,
                unit_type: $unit->unit_type,
            ))
            ->all();
    }

    public function offeringUnit(int $unitId): ?CourseOfferingUnitReference
    {
        $unit = Unit::query()->find($unitId, ['id', 'code', 'name', 'credit_points', 'level', 'unit_type']);

        return $unit === null ? null : $this->mapUnit($unit);
    }

    public function programIdForOfferingUnit(int $unitId): ?int
    {
        return CurriculumUnit::query()
            ->where('unit_id', $unitId)
            ->with('curriculumVersion:id,program_id')
            ->orderBy('id')
            ->first()
            ?->curriculumVersion
            ?->program_id;
    }

    /**
     * @return list<CourseOfferingSyllabusTemplateReference>
     */
    public function offeringSyllabusTemplates(?int $unitId = null, ?int $currentSyllabusTemplateId = null): array
    {
        return SyllabusTemplate::query()
            ->where('is_active', true)
            ->when($unitId !== null, fn ($query) => $query->where('unit_id', $unitId))
            ->assignableToCourseOffering($currentSyllabusTemplateId)
            ->with(['unit:id,code,name', 'applicableCampus:id,name', 'applicableProgram:id,name'])
            ->orderBy('title')
            ->get(['id', 'unit_id', 'title', 'version', 'description', 'applicable_campus_id', 'applicable_program_id', 'delivery_mode'])
            ->map(fn (SyllabusTemplate $template): CourseOfferingSyllabusTemplateReference => $this->mapSyllabusTemplate($template))
            ->all();
    }

    public function offeringSyllabusTemplate(int $syllabusTemplateId): ?CourseOfferingSyllabusTemplateReference
    {
        $template = SyllabusTemplate::query()
            ->with(['unit:id,code,name', 'applicableCampus:id,name', 'applicableProgram:id,name'])
            ->find($syllabusTemplateId, ['id', 'unit_id', 'title', 'version', 'description', 'applicable_campus_id', 'applicable_program_id', 'delivery_mode']);

        return $template === null ? null : $this->mapSyllabusTemplate($template);
    }

    public function hasAcademicPeriod(int $academicPeriodId): bool
    {
        return Semester::query()->whereKey($academicPeriodId)->exists();
    }

    public function hasUnit(int $unitId): bool
    {
        return Unit::query()->whereKey($unitId)->exists();
    }

    public function hasSyllabusTemplate(int $syllabusTemplateId, ?int $unitId = null): bool
    {
        return SyllabusTemplate::query()
            ->whereKey($syllabusTemplateId)
            ->when($unitId !== null, fn ($query) => $query->where('unit_id', $unitId))
            ->exists();
    }

    public function isSyllabusTemplateAssignable(int $syllabusTemplateId, ?int $currentSyllabusTemplateId = null): bool
    {
        if (! $this->hasSyllabusTemplate($syllabusTemplateId)) {
            return true;
        }

        return SyllabusTemplate::query()
            ->assignableToCourseOffering($currentSyllabusTemplateId)
            ->whereKey($syllabusTemplateId)
            ->exists();
    }

    private function nullableDate(mixed $date): ?CarbonImmutable
    {
        return $date === null ? null : CarbonImmutable::parse($date);
    }

    private function mapUnit(Unit $unit): CourseOfferingUnitReference
    {
        return new CourseOfferingUnitReference(
            id: (int) $unit->id,
            code: (string) $unit->code,
            name: (string) $unit->name,
            credit_points: (string) $unit->credit_points,
            level: $unit->level === null ? null : (int) $unit->level,
            unit_type: $unit->unit_type,
        );
    }

    private function mapSyllabusTemplate(SyllabusTemplate $template): CourseOfferingSyllabusTemplateReference
    {
        return new CourseOfferingSyllabusTemplateReference(
            id: (int) $template->id,
            unit_id: (int) $template->unit_id,
            title: $template->title,
            version: $template->version,
            description: $template->description,
            delivery_mode: $template->delivery_mode,
            unit: $template->unit === null ? null : [
                'id' => (int) $template->unit->id,
                'code' => (string) $template->unit->code,
                'name' => (string) $template->unit->name,
            ],
            applicable_campus: $template->applicableCampus === null ? null : [
                'id' => (int) $template->applicableCampus->id,
                'name' => (string) $template->applicableCampus->name,
            ],
            applicable_program: $template->applicableProgram === null ? null : [
                'id' => (int) $template->applicableProgram->id,
                'name' => (string) $template->applicableProgram->name,
            ],
        );
    }
}
