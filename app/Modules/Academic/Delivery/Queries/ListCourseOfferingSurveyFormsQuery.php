<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Queries;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ListCourseOfferingSurveyFormsQuery
{
    /** @return Collection<int, object> */
    public function handle(): Collection
    {
        return DB::table('forms')
            ->where('type', 'survey')
            ->where('status', 'active')
            ->get(['id', 'title', 'code']);
    }
}
