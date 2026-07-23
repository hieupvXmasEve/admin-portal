<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Actions;

use App\Models\ApplicationGuardian;
use App\Modules\Admissions\Exceptions\ApplicationLifecycleException;
use App\Modules\Admissions\Support\ApplicantGuardianManager;

final class UpdateApplicantGuardianAction
{
    /** @param array{guardian: ApplicationGuardian, attributes: array<string,mixed>} $data */
    public static function run(array $data): ApplicationGuardian
    {
        if (! $data['guardian']->studentApplication->isPending()) {
            throw new ApplicationLifecycleException('Guardians can only be changed while the application is pending.');
        }

        return app(ApplicantGuardianManager::class)->update($data['guardian'], $data['attributes']);
    }
}
