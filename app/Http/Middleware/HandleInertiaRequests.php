<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Platform\Queries\GetSystemBrandingQuery;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\DTO\AcademicPeriodReference;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use App\Support\SemesterContextResolver;
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
            'system_config' => fn (): array => app(GetSystemBrandingQuery::class)->handle(),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => function () use ($user, $currentCampusId) {
                if (! $user) {
                    return null;
                }

                $permissionReader = app(CampusPermissionReader::class);

                return [
                    'user' => $user->only('id', 'name', 'email'),
                    'permissions' => $permissionReader->permissionCodesForUserId((int) $user->id, $currentCampusId),
                    'current_campus_id' => $currentCampusId,
                    'current_campus' => $currentCampusId ? app('campus') : null,
                ];
            },
            'semester' => function () use ($user, $currentCampusId) {
                if (! $user) {
                    return null;
                }

                $permissions = app(CampusPermissionReader::class)->permissionCodesForUserId((int) $user->id, $currentCampusId);
                $semesterContextPerms = [
                    'view_finance_student_overview',
                    'view_finance_audit_workspace',
                    'view_finance_operations_dashboard',
                    'view_finance_cockpit',
                    'view_finance_batch_studio',
                    'view_finance_reporting',
                    'view_course_offering',
                ];
                if (count(array_intersect($semesterContextPerms, $permissions)) === 0) {
                    return null; // bound cost: only semester-aware surfaces pay for the query
                }

                return [
                    'selected_id' => SemesterContextResolver::selectedId(),
                    'options' => array_map(
                        fn (AcademicPeriodReference $period): array => [
                            'id' => $period->id,
                            'code' => $period->code,
                            'name' => $period->name,
                            'is_active' => $period->is_current,
                        ],
                        app(AcademicPeriodReader::class)->selectable(),
                    ),
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
