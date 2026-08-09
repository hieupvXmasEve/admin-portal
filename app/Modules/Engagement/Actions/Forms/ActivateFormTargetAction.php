<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions\Forms;

use App\Modules\Engagement\Models\FormTarget;
use Illuminate\Support\Facades\DB;

class ActivateFormTargetAction
{
    public function execute(FormTarget $target): FormTarget
    {
        return DB::transaction(function () use ($target) {
            $updates = [
                'status' => 'active',
                'campus_id' => session('current_campus_id'),
            ];

            if ($target->form->type === 'query') {
                $updates['submission_limit_per_user'] = null;
            }

            $target->update($updates);

            // Trigger assignments if mandatory and it's a survey
            if ($target->is_mandatory && $target->form->type === 'survey') {
                app(GenerateStudentAssignmentsAction::class)->execute($target);
            }

            return $target;
        });
    }
}
