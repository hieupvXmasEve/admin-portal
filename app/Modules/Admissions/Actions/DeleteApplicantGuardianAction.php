<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Actions;

use App\Models\ApplicationGuardian;
use App\Modules\Admissions\Exceptions\ApplicationLifecycleException;
use App\Modules\Admissions\Support\ApplicantGuardianManager;

final class DeleteApplicantGuardianAction
{
    /** @param array{guardian: ApplicationGuardian} $data */
    public static function run(array $data): void
    {
        if (! $data['guardian']->studentApplication->isPending()) {
            throw new ApplicationLifecycleException('Guardians can only be changed while the application is pending.');
        }

        app(ApplicantGuardianManager::class)->delete($data['guardian']);
    }
}
