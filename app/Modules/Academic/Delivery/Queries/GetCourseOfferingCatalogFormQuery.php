<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use App\Shared\Contracts\Identity\AvailableLecturerReader;

class GetCourseOfferingCatalogFormQuery
{
    public function __construct(
        private readonly CourseOfferingCatalogReader $catalog,
        private readonly AvailableLecturerReader $lecturers,
    ) {}

    /**
     * @return array{active_semester: array{id: int, name: string, code: string, start_date: mixed, end_date: mixed}|null, semesters: list<array{id: int, name: string, code: string, start_date: mixed, end_date: mixed, is_current: bool}>, units: list<array{unit_id: int, code: string, name: string, credit_points: string, level: int|null, unit_type: string|null}>, lectures: list<array{id: int, first_name: string, last_name: string, email: string, academic_rank: string|null}>, syllabus_templates: list<array<string, mixed>>, semester: array{id: int, name: string, code: string, start_date: mixed, end_date: mixed}|null, unit: array{id: int, code: string, name: string, credit_points: string}|null, syllabus_template: array{id: int, title: string|null, version: string|null, description: string|null}|null}
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
            'semesters' => array_map(
                static fn ($period): array => [
                    'id' => $period->id,
                    'name' => $period->name,
                    'code' => $period->code,
                    'start_date' => $period->start_date,
                    'end_date' => $period->end_date,
                    'is_current' => $period->is_current,
                ],
                $this->catalog->offeringPeriods(),
            ),
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
     * @return list<array{id: int, first_name: string, last_name: string, email: string, academic_rank: string|null}>
     */
    private function availableLectures(): array
    {
        return $this->lecturers->all();
    }
}
