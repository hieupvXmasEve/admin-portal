<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Models\Lecture;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use Illuminate\Database\Eloquent\Collection;

class GetCourseOfferingCatalogFormQuery
{
    public function __construct(
        private readonly CourseOfferingCatalogReader $catalog,
    ) {}

    /**
     * @return array{active_semester: array{id: int, name: string, code: string, start_date: mixed, end_date: mixed}|null, units: list<array{unit_id: int, code: string, name: string, credit_points: string, level: int|null, unit_type: string|null}>, lectures: Collection<int, Lecture>, syllabus_templates: list<array<string, mixed>>, semester: array{id: int, name: string, code: string, start_date: mixed, end_date: mixed}|null, unit: array{id: int, code: string, name: string, credit_points: string}|null, syllabus_template: array{id: int, title: string|null, version: string|null, description: string|null}|null}
     */
    public function handle(?int $academicPeriodId = null, ?int $unitId = null, ?int $syllabusTemplateId = null): array
    {
        $activePeriod = $academicPeriodId === null
            ? $this->catalog->currentOfferingPeriod()
            : $this->catalog->offeringPeriod($academicPeriodId);
        $unit = $unitId === null ? null : $this->catalog->offeringUnit($unitId);
        $syllabusTemplate = $syllabusTemplateId === null
            ? null
            : $this->catalog->offeringSyllabusTemplate($syllabusTemplateId);

        return [
            'active_semester' => $activePeriod === null ? null : [
                'id' => $activePeriod->id,
                'name' => $activePeriod->name,
                'code' => $activePeriod->code,
                'start_date' => $activePeriod->start_date,
                'end_date' => $activePeriod->end_date,
            ],
            'units' => array_map(
                static fn ($unit): array => $unit->toArray(),
                $this->catalog->offeringUnits(),
            ),
            'lectures' => $this->availableLectures(),
            'syllabus_templates' => array_map(
                static fn ($template): array => $template->toArray(),
                $this->catalog->offeringSyllabusTemplates($unitId, $syllabusTemplateId),
            ),
            'semester' => $activePeriod === null ? null : [
                'id' => $activePeriod->id,
                'name' => $activePeriod->name,
                'code' => $activePeriod->code,
                'start_date' => $activePeriod->start_date,
                'end_date' => $activePeriod->end_date,
            ],
            'unit' => $unit === null ? null : [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'credit_points' => $unit->credit_points,
            ],
            'syllabus_template' => $syllabusTemplate === null ? null : [
                'id' => $syllabusTemplate->id,
                'title' => $syllabusTemplate->title,
                'version' => $syllabusTemplate->version,
                'description' => $syllabusTemplate->description,
            ],
        ];
    }

    /**
     * @return Collection<int, Lecture>
     */
    private function availableLectures(): Collection
    {
        return Lecture::active()
            ->availableForAssignment()
            ->orderByName()
            ->get(['id', 'first_name', 'last_name', 'email', 'academic_rank']);
    }
}
