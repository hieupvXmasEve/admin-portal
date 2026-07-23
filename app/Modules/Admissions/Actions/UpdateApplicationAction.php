<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Actions;

use App\Models\StudentApplication;

final class UpdateApplicationAction
{
    /** @param array{application: StudentApplication, attributes: array<string, mixed>} $data */
    public static function run(array $data): StudentApplication
    {
        $data['application']->update($data['attributes']);

        return $data['application']->refresh();
    }
}
