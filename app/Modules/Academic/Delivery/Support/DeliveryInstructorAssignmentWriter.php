<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Support;

use App\Modules\Academic\Delivery\Actions\AssignInstructorAction;
use App\Shared\Contracts\Academic\InstructorAssignmentWriter;

final class DeliveryInstructorAssignmentWriter implements InstructorAssignmentWriter
{
    public function assign(int $courseOfferingId, int $lecturerId): void
    {
        AssignInstructorAction::run([
            'course_offering_id' => $courseOfferingId,
            'lecture_id' => $lecturerId,
        ]);
    }
}
