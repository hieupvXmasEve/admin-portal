<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\AcademicPeriodReference;
use App\Shared\Contracts\Academic\DTO\CourseOfferingSyllabusTemplateReference;
use App\Shared\Contracts\Academic\DTO\CourseOfferingUnitReference;

interface CourseOfferingCatalogReader
{
    public function currentOfferingPeriod(): ?AcademicPeriodReference;

    public function offeringPeriod(int $academicPeriodId): ?AcademicPeriodReference;

    /**
     * @return list<CourseOfferingUnitReference>
     */
    public function offeringUnits(): array;

    public function offeringUnit(int $unitId): ?CourseOfferingUnitReference;

    public function programIdForOfferingUnit(int $unitId): ?int;

    /**
     * @return list<CourseOfferingSyllabusTemplateReference>
     */
    public function offeringSyllabusTemplates(?int $unitId = null, ?int $currentSyllabusTemplateId = null): array;

    public function offeringSyllabusTemplate(int $syllabusTemplateId): ?CourseOfferingSyllabusTemplateReference;

    public function hasAcademicPeriod(int $academicPeriodId): bool;

    public function hasUnit(int $unitId): bool;

    public function hasSyllabusTemplate(int $syllabusTemplateId, ?int $unitId = null): bool;

    public function isSyllabusTemplateAssignable(int $syllabusTemplateId, ?int $currentSyllabusTemplateId = null): bool;
}
