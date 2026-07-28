<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\Warnings;

use App\Models\AcademicWarningSetting;
use App\Models\User;

class UpdateWarningSettingsAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function run(AcademicWarningSetting $settings, array $data, User $actor): AcademicWarningSetting
    {
        $settings->fill([
            'attendance_warning_ratio' => $data['attendance_warning_ratio'],
            'channels' => $data['channels'],
            'academic_warning_title' => $data['academic_warning_title'],
            'academic_warning_body' => $data['academic_warning_body'],
            'attendance_warning_title' => $data['attendance_warning_title'],
            'attendance_warning_body' => $data['attendance_warning_body'],
            'attendance_exceeded_title' => $data['attendance_exceeded_title'],
            'attendance_exceeded_body' => $data['attendance_exceeded_body'],
            'updated_by_user_id' => $actor->id,
        ])->save();

        return $settings->refresh();
    }
}
