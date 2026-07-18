<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Modules\Academic\Delivery\Actions\MoveStudentBetweenCourseOfferingSectionsAction;

final class MoveStudentToSectionAction
{
    /**
     * Move a student from one course offering to another.
     *
     * @param  array  $data  [student_id, target_course_offering_id, force_move]
     *
     * @throws ValidationException
     */
    public static function run(array $data): void
    {
        MoveStudentBetweenCourseOfferingSectionsAction::run($data);
    }
}
