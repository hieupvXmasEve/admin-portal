<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SystemConfigService;
use Inertia\Inertia;
use Inertia\Response;

class SystemConfigController extends Controller
{
    public function __construct(
        private SystemConfigService $systemConfigService
    ) {}

    /**
     * Display the system configuration page
     */
    public function index(): Response
    {
        $config = $this->systemConfigService->getConfig();
        
        return Inertia::render('SystemConfig/Index', [
            'config' => $config,
        ]);
    }
}
