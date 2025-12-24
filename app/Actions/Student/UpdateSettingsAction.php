<?php

namespace App\Actions\Student;

use App\Models\Student;
use App\Models\StudentSetting;

class UpdateSettingsAction
{
    /**
     * Update student settings by merging new values with existing ones.
     *
     * @param Student $student
     * @param array $newSettings Partial settings array
     * @return StudentSetting
     */
    public function execute(Student $student, array $newSettings): StudentSetting
    {
        $currentSettingsModel = $student->settings;
        $currentSettings = $currentSettingsModel?->settings ?? $this->getDefaultSettings();

        // Deep merge settings
        $updatedSettings = array_replace_recursive($currentSettings, $newSettings);

        return StudentSetting::updateOrCreate(
            ['student_id' => $student->id],
            ['settings' => $updatedSettings]
        );
    }

    protected function getDefaultSettings(): array
    {
        return [
            'ui' => [
                'theme' => 'system',
                'language' => 'vi',
                'compact_mode' => false,
            ],
            'notifications' => [
                'email' => true,
                'push' => false,
            ],
        ];
    }
}
