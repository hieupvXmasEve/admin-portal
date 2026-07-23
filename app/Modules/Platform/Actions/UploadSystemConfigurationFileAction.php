<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Support\SystemConfigurationAuditLogger;
use App\Modules\Platform\Support\SystemConfigurationStore;
use Illuminate\Http\UploadedFile;

final class UploadSystemConfigurationFileAction
{
    /**
     * @param  array{file: UploadedFile, configuration_key: string}  $data
     * @return array{path: string, stored_path: string, cache_bust: int}
     */
    public static function run(array $data): array
    {
        $file = $data['file'];
        $configurationKey = $data['configuration_key'];

        $result = app(SystemConfigurationStore::class)->upload($file, $configurationKey);

        app(SystemConfigurationAuditLogger::class)->record('uploaded', [$configurationKey]);

        return $result;
    }
}
