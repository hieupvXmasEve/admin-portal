<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Exceptions\CourseOfferingDeletionException;
use App\Shared\Contracts\Academic\CourseOfferingCatalogReader;
use Illuminate\Support\Facades\DB;

final class BulkDeleteCourseOfferingsAction
{
    public function __construct(private readonly CourseOfferingCatalogReader $catalog) {}

    /** @param array{course_offering_ids: list<int>, campus_id: int} $data */
    public static function run(array $data): int
    {
        return app(self::class)->handle($data);
    }

    /** @param array{course_offering_ids: list<int>, campus_id: int} $data */
    public function handle(array $data): int
    {
        return DB::transaction(function () use ($data): int {
            $offerings = CourseOffering::query()
                ->whereIn('id', $data['course_offering_ids'])
                ->where('campus_id', $data['campus_id'])
                ->lockForUpdate()
                ->get();
            $cannotDelete = [];

            foreach ($offerings as $offering) {
                $reasons = [];
                $sessions = $offering->classSessions()->count();
                if ($sessions > 0) {
                    $reasons[] = "{$sessions} scheduled session(s)";
                }
                if ((int) $offering->current_enrollment > 0) {
                    $reasons[] = "{$offering->current_enrollment} enrolled student(s)";
                }
                if ($reasons !== []) {
                    $unitCode = $this->catalog->offeringUnit((int) $offering->unit_id)?->code ?? 'Unknown';
                    $cannotDelete[] = "{$unitCode}: ".implode(' and ', $reasons);
                }
            }

            if ($cannotDelete !== []) {
                throw new CourseOfferingDeletionException(
                    'Cannot delete the following course offerings: '.implode('; ', $cannotDelete).'. Please cancel these course offerings instead or remove all sessions and students first.',
                );
            }

            $registrationCount = 0;
            foreach ($offerings as $offering) {
                $registrationCount += RemoveCourseOfferingRosterAction::run([
                    'course_offering_id' => (int) $offering->id,
                ]);
                $offering->delete();
            }

            return $registrationCount;
        });
    }
}
