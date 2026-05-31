<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

final class WarningMessageRenderer
{
    /**
     * @param  array<string, scalar|null>  $variables
     */
    public static function render(string $template, array $variables): string
    {
        $replace = [];

        foreach ($variables as $key => $value) {
            $replace['{{'.$key.'}}'] = (string) ($value ?? '');
        }

        return strtr($template, $replace);
    }
}
