<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Actions;

use App\Models\StudentApplication;
use App\Modules\Admissions\Exceptions\ApplicationLifecycleException;

final class DeleteApplicationAction
{
    /** @param array{application: StudentApplication} $data */
    public static function run(array $data): void
    {
        if ($data['application']->isConverted()) {
            throw new ApplicationLifecycleException('Cannot delete an application that is linked to a student.');
        }

        $data['application']->delete();
    }
}
