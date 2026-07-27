<?php

declare(strict_types=1);

/*
 | Brand theme index.
 |
 | Each file in config/themes/ is one preset, named after its file. Adding a
 | brand means dropping a file there and pointing APP_THEME at its name; no
 | code change is needed. Unknown APP_THEME values fall back to 'default'.
 */

$presets = [];

foreach (glob(__DIR__.'/themes/*.php') ?: [] as $file) {
    $presets[basename($file, '.php')] = require $file;
}

return [
    'active' => env('APP_THEME', 'green'),
    'default' => 'green',
    'presets' => $presets,
];
