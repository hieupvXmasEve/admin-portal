<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions\Forms;

use App\Modules\Engagement\Models\Form;
use App\Modules\Engagement\Models\FormTarget;
use Illuminate\Validation\ValidationException;

class CreateFormTargetAction
{
    public function execute(array $data): FormTarget
    {
        // 1. Validate Form and Version
        $form = Form::findOrFail($data['form_id']);

        $versionId = $data['form_version_id'] ?? null;
        if (! $versionId) {
            // Get latest published version
            $latestVersion = $form->versions()->where('is_published', true)->latest()->first();
            if (! $latestVersion) {
                throw ValidationException::withMessages(['form_id' => 'This form has no published versions.']);
            }
            $versionId = $latestVersion->id;
        }

        // 2. Validate Constraints
        // Course Offering unique constraint (managed by logic)
        if ($data['scope_type'] === 'course') {
            $exists = FormTarget::where('scope_type', 'course')
                ->where('scope_id', $data['scope_id'])
                ->where('form_id', $data['form_id'])
                ->where('start_at', '<=', $data['end_at'] ?? now()->addYear()) // Overlap check roughly
                ->where('end_at', '>=', $data['start_at'])
                ->exists();

            // SRS says: "Unique: (context_type, context_id) áp dụng cho course survey" (BR-04)
            // Strictly: 1 course offering has 0 or 1 survey run.
            $alreadyHasRun = FormTarget::where('scope_type', 'course')
                ->where('scope_id', $data['scope_id'])
                ->exists();

            if ($alreadyHasRun) {
                throw ValidationException::withMessages(['scope_id' => 'This course offering already has a survey run.']);
            }
        }

        // 3. Create Target
        return FormTarget::create([
            'form_id' => $data['form_id'],
            'form_version_id' => $versionId,
            'campus_id' => $data['campus_id'] ?? null, // optional if global or inferred
            'scope_type' => $data['scope_type'],
            'scope_id' => $data['scope_id'] ?? null,
            'semester_id' => $data['semester_id'] ?? null, // For department + semester
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'] ?? null,
            'status' => $data['status'] ?? 'draft', // default draft
            'is_mandatory' => $data['is_mandatory'] ?? false,
            'submission_limit_per_user' => $data['submission_limit_per_user'] ?? 1,
        ]);
    }
}
