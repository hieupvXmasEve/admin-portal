<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Exceptions\CourseOfferingDuplicationException;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use Illuminate\Support\Facades\DB;

final class DuplicateCourseOfferingAction
{
    public function __construct(private readonly CourseOfferingCatalogReader $catalog) {}

    /** @param array{course_offering_id: int, campus_id: int} $data */
    public static function run(array $data): CourseOffering
    {
        return app(self::class)->handle($data);
    }

    /** @param array{course_offering_id: int, campus_id: int} $data */
    public function handle(array $data): CourseOffering
    {
        return DB::transaction(function () use ($data): CourseOffering {
            $courseOffering = CourseOffering::query()
                ->whereKey($data['course_offering_id'])
                ->where('campus_id', $data['campus_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $courseOffering->syllabus_template_id !== null
                && ! $this->catalog->isSyllabusTemplateAssignable((int) $courseOffering->syllabus_template_id)
            ) {
                throw new CourseOfferingDuplicationException(
                    'Cannot duplicate a Canvas-linked course offering. Create a new offering and select a reusable syllabus template.',
                );
            }

            $attributes = $courseOffering->getAttributes();
            unset(
                $attributes['id'],
                $attributes['lecture_id'],
                $attributes['current_enrollment'],
                $attributes['current_waitlist'],
                $attributes['created_at'],
                $attributes['updated_at'],
                $attributes['deleted_at'],
            );

            $attributes['current_enrollment'] = 0;
            $attributes['current_waitlist'] = 0;

            if ($courseOffering->section_code) {
                $attributes['section_code'] = $this->nextSectionCode($courseOffering);
            }

            return CourseOffering::query()->create($attributes);
        });
    }

    private function nextSectionCode(CourseOffering $courseOffering): string
    {
        $baseSectionCode = $courseOffering->section_code;
        $counter = 1;

        do {
            $sectionCode = $baseSectionCode.'_copy'.($counter > 1 ? $counter : '');
            $exists = CourseOffering::query()
                ->where('semester_id', $courseOffering->semester_id)
                ->where('unit_id', $courseOffering->unit_id)
                ->where('campus_id', $courseOffering->campus_id)
                ->where('section_code', $sectionCode)
                ->exists();
            $counter++;
        } while ($exists && $counter <= 100);

        return $sectionCode;
    }
}
