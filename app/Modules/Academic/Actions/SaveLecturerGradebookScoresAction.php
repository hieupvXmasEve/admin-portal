<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\CourseOffering;

/** @deprecated Resolve the Delivery action instead. */
class SaveLecturerGradebookScoresAction
{
    /**
     * @param  array{
     *   course_offering: CourseOffering,
     *   scores: array<int, array{detail_id:int, student_id:int, points_earned:int|float|string}>,
     *   lecturer_id: int,
     * }  $data
     * @return array<string, mixed>
     */
    public static function run(array $data): array
    {
        return \App\Modules\Academic\Delivery\Actions\SaveLecturerGradebookScoresAction::run($data);
    }

    /**
     * @param  array<int, array{detail_id:int, student_id:int, points_earned:int|float|string}>  $scores
     * @return array<string, mixed>
     */
    public function handle(CourseOffering $courseOffering, array $scores, int $lecturerId): array
    {
        return self::run([
            'course_offering' => $courseOffering,
            'scores' => $scores,
            'lecturer_id' => $lecturerId,
        ]);
    }
}
