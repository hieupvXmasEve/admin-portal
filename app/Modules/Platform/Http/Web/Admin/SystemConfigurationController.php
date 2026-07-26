<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Actions\UpdateSystemConfigurationAction;
use App\Modules\Platform\Actions\UploadSystemConfigurationFileAction;
use App\Modules\Platform\Http\Requests\SystemConfiguration\UpdateSystemConfigurationRequest;
use App\Modules\Platform\Http\Requests\SystemConfiguration\UploadSystemConfigurationFileRequest;
use App\Modules\Platform\Queries\GetSystemBrandingQuery;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class SystemConfigurationController extends Controller
{
    public function __construct(private readonly GetSystemBrandingQuery $branding) {}

    public function index(): Response
    {
        return Inertia::render('SystemConfig/Index', [
            'config' => $this->branding->handle(),
            'permissions' => [
                'can_manage' => request()->user()?->hasSystemRole('super_admin') ?? false,
            ],
        ]);
    }

    public function update(UpdateSystemConfigurationRequest $request): RedirectResponse
    {
        UpdateSystemConfigurationAction::run($request->validated());

        Inertia::flash('success', 'System configuration updated.');

        return to_route('system.config.index');
    }

    public function upload(UploadSystemConfigurationFileRequest $request): RedirectResponse
    {
        UploadSystemConfigurationFileAction::run([
            'file' => $request->file('file'),
            'slot' => $request->string('slot')->toString(),
        ]);

        Inertia::flash('success', 'Branding asset updated.');

        return to_route('system.config.index');
    }
}
