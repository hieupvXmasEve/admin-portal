<?php

declare(strict_types=1);

$greenLight = [
    '--primary' => 'oklch(0.527 0.154 150.069)',
    '--primary-foreground' => 'oklch(0.982 0.018 155.826)',
    '--accent' => 'oklch(0.527 0.154 150.069)',
    '--accent-foreground' => 'oklch(0.982 0.018 155.826)',
    '--sidebar-primary' => 'oklch(0.627 0.194 149.214)',
    '--sidebar-primary-foreground' => 'oklch(0.982 0.018 155.826)',
    '--sidebar-accent' => 'oklch(0.527 0.154 150.069)',
    '--sidebar-accent-foreground' => 'oklch(0.982 0.018 155.826)',
    '--chart-1' => 'oklch(0.871 0.15 154.449)',
    '--chart-2' => 'oklch(0.723 0.219 149.579)',
    '--chart-3' => 'oklch(0.627 0.194 149.214)',
    '--chart-4' => 'oklch(0.527 0.154 150.069)',
    '--chart-5' => 'oklch(0.448 0.119 151.328)',
];

$greenDark = [
    '--primary' => 'oklch(0.448 0.119 151.328)',
    '--primary-foreground' => 'oklch(0.982 0.018 155.826)',
    '--accent' => 'oklch(0.448 0.119 151.328)',
    '--accent-foreground' => 'oklch(0.982 0.018 155.826)',
    '--sidebar-primary' => 'oklch(0.723 0.219 149.579)',
    '--sidebar-primary-foreground' => 'oklch(0.982 0.018 155.826)',
    '--sidebar-accent' => 'oklch(0.448 0.119 151.328)',
    '--sidebar-accent-foreground' => 'oklch(0.982 0.018 155.826)',
    '--chart-1' => 'oklch(0.871 0.15 154.449)',
    '--chart-2' => 'oklch(0.723 0.219 149.579)',
    '--chart-3' => 'oklch(0.627 0.194 149.214)',
    '--chart-4' => 'oklch(0.527 0.154 150.069)',
    '--chart-5' => 'oklch(0.448 0.119 151.328)',
];

$orangeLight = [
    '--primary' => 'oklch(0.627 0.19 48)',
    '--primary-foreground' => 'oklch(0.985 0.01 48)',
    '--accent' => 'oklch(0.627 0.19 48)',
    '--accent-foreground' => 'oklch(0.985 0.01 48)',
    '--sidebar-primary' => 'oklch(0.72 0.17 50)',
    '--sidebar-primary-foreground' => 'oklch(0.985 0.01 48)',
    '--sidebar-accent' => 'oklch(0.627 0.19 48)',
    '--sidebar-accent-foreground' => 'oklch(0.985 0.01 48)',
    '--chart-1' => 'oklch(0.87 0.14 52)',
    '--chart-2' => 'oklch(0.75 0.18 50)',
    '--chart-3' => 'oklch(0.65 0.17 49)',
    '--chart-4' => 'oklch(0.627 0.19 48)',
    '--chart-5' => 'oklch(0.52 0.14 46)',
];

$orangeDark = [
    '--primary' => 'oklch(0.52 0.14 46)',
    '--primary-foreground' => 'oklch(0.985 0.01 48)',
    '--accent' => 'oklch(0.52 0.14 46)',
    '--accent-foreground' => 'oklch(0.985 0.01 48)',
    '--sidebar-primary' => 'oklch(0.75 0.18 50)',
    '--sidebar-primary-foreground' => 'oklch(0.985 0.01 48)',
    '--sidebar-accent' => 'oklch(0.52 0.14 46)',
    '--sidebar-accent-foreground' => 'oklch(0.985 0.01 48)',
    '--chart-1' => 'oklch(0.87 0.14 52)',
    '--chart-2' => 'oklch(0.75 0.18 50)',
    '--chart-3' => 'oklch(0.65 0.17 49)',
    '--chart-4' => 'oklch(0.627 0.19 48)',
    '--chart-5' => 'oklch(0.52 0.14 46)',
];

return [
    'active' => env('APP_THEME', 'green'),
    'default' => 'green',

    'presets' => [
        'green' => [
            'light' => $greenLight,
            'dark' => $greenDark,
        ],
        'orange' => [
            'light' => $orangeLight,
            'dark' => $orangeDark,
        ],
    ],
];
