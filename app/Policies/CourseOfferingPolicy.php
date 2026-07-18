<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CourseOffering;
use App\Models\Lecture;

class CourseOfferingPolicy
{
    public function viewGradebook(Lecture $lecturer, CourseOffering $courseOffering): bool
    {
        return $courseOffering->classSessions()
            ->where('lecture_id', $lecturer->id)
            ->exists();
    }
}
