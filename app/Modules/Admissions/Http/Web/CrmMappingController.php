<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Modules\Admissions\Http\Requests\Admissions\SaveCrmValueMappingRequest;
use App\Modules\Admissions\Models\CrmValueMapping;
use App\Modules\Admissions\Queries\ListUnmappedCrmValuesQuery;
use App\Modules\Admissions\Services\CrmMappingResolver;
use App\Modules\Admissions\Support\Crm\CrmMappingSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** Admissions-owned staff screen for resolving raw CRM values to local codes (Phase 4). */
final class CrmMappingController extends Controller
{
    public function index(ListUnmappedCrmValuesQuery $unmapped, CrmMappingSettings $settings): mixed
    {
        $campus = app()->bound('campus') ? app('campus') : null;

        return Inertia::render('StudentApplications/CrmMappings', [
            'unmapped' => $unmapped->handle($campus?->code),
            'intake' => ['crm_value' => CrmValueMapping::INTAKE_DEFAULT_KEY, 'local_code' => $settings->getIntakeCode()],
            'semesters' => Semester::query()->orderByDesc('id')->get(['code', 'name']),
        ]);
    }

    public function store(SaveCrmValueMappingRequest $request, CrmMappingSettings $settings, CrmMappingResolver $resolver): RedirectResponse
    {
        $data = $request->validated();

        if ($data['kind'] === CrmValueMapping::KIND_INTAKE) {
            $settings->setIntakeCode($data['local_code']);
        } else {
            CrmValueMapping::query()->updateOrCreate(
                ['kind' => $data['kind'], 'crm_value' => $data['crm_value']],
                ['local_code' => $data['local_code']],
            );
        }

        $result = $resolver->resolve();

        return back()->with($result['skipped']
            ? ['warning' => 'Mapping saved. A sync is currently running, so applications will pick it up on the next resolve.']
            : ['success' => "Mapping saved. {$result['updated']} application(s) updated."]);
    }
}
