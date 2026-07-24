<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Actions;

use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Support\ClassSessionService;

final class CreateClassSessionAction
{
    /** @param array<string, mixed> $data */
    public static function run(array $data): ClassSession
    {
        $courseOffering = CourseOffering::query()
            ->with('syllabusTemplate')
            ->findOrFail($data['course_offering_id']);

        $maximumSessions = $courseOffering->syllabusTemplate?->total_sessions;
        if ($maximumSessions !== null && ClassSession::query()->where('course_offering_id', $courseOffering->id)->count() >= $maximumSessions) {
            throw new \DomainException("Cannot create session. Course offering has reached the maximum of {$maximumSessions} sessions allowed by the syllabus template.");
        }

        return app(ClassSessionService::class)->createClassSession($data);
    }
}
