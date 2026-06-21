<?php

declare(strict_types=1);

namespace App\Modules\AI\Contracts;

use App\Modules\AI\Models\AiProviderSetting;
use App\Modules\AI\Support\AiProviderTestResult;

interface AiProviderTester
{
    public function test(AiProviderSetting $setting, array $resolvedProvider): AiProviderTestResult;
}
