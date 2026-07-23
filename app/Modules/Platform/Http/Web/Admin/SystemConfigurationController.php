<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Queries\GetSystemConfigurationQuery;
use Inertia\Inertia;
use Inertia\Response;

final class SystemConfigurationController extends Controller
{
    public function __construct(private readonly GetSystemConfigurationQuery $configuration) {}

    public function index(): Response
    {
        return Inertia::render('SystemConfig/Index', [
            'config' => $this->configuration->handle(),
            'permissions' => [
                'can_manage' => request()->user()?->can('manage_system_config') ?? false,
            ],
        ]);
    }
}
