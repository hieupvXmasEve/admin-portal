<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Queries\Forms;

use App\Modules\Engagement\Models\Form;
use Illuminate\Support\Collection;

final class ListActiveSurveyFormsQuery
{
    /**
     * @return Collection<int, Form>
     */
    public function execute(): Collection
    {
        return Form::query()
            ->where('type', 'survey')
            ->where('status', 'active')
            ->get(['id', 'title', 'code']);
    }
}
