<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Academic;

use App\Shared\Contracts\Academic\DTO\CourseOfferingSurveyContext;

interface CourseOfferingSurveyContextReader
{
    public function forOffering(int $courseOfferingId): CourseOfferingSurveyContext;
}
