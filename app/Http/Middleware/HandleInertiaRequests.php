<?php

namespace App\Http\Middleware;

use App\Models\Semester;
use App\Services\PermissionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();
        $currentCampusId = session('current_campus_id');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => function () use ($user, $currentCampusId) {
                if (! $user) {
                    return null;
                }

                $permissionService = app(PermissionService::class);

                return [
                    'user' => $user->only('id', 'name', 'email'),
                    'permissions' => $permissionService->getUserPermissions($user, $currentCampusId),
                    'current_campus_id' => $currentCampusId,
                    'current_campus' => $currentCampusId ? app('campus') : null,
                ];
            },
            'semester' => function () use ($user, $currentCampusId) {
                if (! $user) {
                    return null;
                }

                $permissions = app(PermissionService::class)->getUserPermissions($user, $currentCampusId);
                $financePerms = ['view_finance_student_overview', 'view_finance_audit_workspace', 'view_finance_operations_dashboard'];
                if (count(array_intersect($financePerms, $permissions)) === 0) {
                    return null; // bound cost: only finance users pay for the semester query
                }

                $selectedId = session('current_semester_id');
                if ($selectedId === null) {
                    $selectedId = Semester::query()->where('is_active', true)->value('id');
                }

                return [
                    'selected_id' => $selectedId !== null ? (int) $selectedId : null,
                    'options' => Semester::query()
                        ->where('is_archived', false)
                        ->orderByDesc('start_date')
                        ->get(['id', 'code', 'name', 'is_active'])
                        ->map(fn (Semester $s) => [
                            'id' => (int) $s->id,
                            'code' => $s->code,
                            'name' => $s->name,
                            'is_active' => (bool) $s->is_active,
                        ])
                        ->all(),
                ];
            },
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
                'route' => $request->route()->getName(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => function () {
                return [
                    'success' => session('success'),
                    'error' => session('error'),
                    'warning' => session('warning'),
                    'info' => session('info'),
                    'message' => session('message'),
                    'batch_errors' => session('batch_errors'),
                    'conversion_summary' => session('conversion_summary'),
                    'success_details' => session('success_details'),
                ];
            },
        ];
    }
}
