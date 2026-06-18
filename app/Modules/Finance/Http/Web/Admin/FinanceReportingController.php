<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanceReportingController extends Controller
{
    private const DEFAULT_VIEW = 'collection-progress';

    /** @var list<array{key: string, label: string, description: string, status: string, obeys_semester: bool}> */
    private const VIEWS = [
        [
            'key' => 'fee-monitor',
            'label' => 'Fee Monitor',
            'description' => 'Expected, generated, missing, blocked, and paid fee completeness.',
            'status' => 'planned',
            'obeys_semester' => true,
        ],
        [
            'key' => 'collection-progress',
            'label' => 'Collection Progress',
            'description' => 'Billed, paid, outstanding, overdue, overpaid, and unapplied balances.',
            'status' => 'planned',
            'obeys_semester' => true,
        ],
        [
            'key' => 'dng-lifecycle',
            'label' => 'DNG/Payment Lifecycle',
            'description' => 'DNG requests, webhooks, payment bridge, invoice, and allocation attention queue.',
            'status' => 'planned',
            'obeys_semester' => false,
        ],
    ];

    public function index(Request $request): Response
    {
        return Inertia::render('Finance/Reporting/Index', [
            'active_view' => $this->activeView((string) $request->query('view', self::DEFAULT_VIEW)),
            'views' => self::VIEWS,
            'computed_at' => now()->toIso8601String(),
            'actions' => [
                'export_enabled' => false,
            ],
        ]);
    }

    private function activeView(string $candidate): string
    {
        $keys = array_column(self::VIEWS, 'key');

        return in_array($candidate, $keys, true) ? $candidate : self::DEFAULT_VIEW;
    }
}
