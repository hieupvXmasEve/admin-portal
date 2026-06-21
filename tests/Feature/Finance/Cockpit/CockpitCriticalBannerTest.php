<?php

declare(strict_types=1);

it('links the critical integrity banner to a concrete invariant drilldown', function () {
    $banner = file_get_contents(base_path('resources/js/components/finance/cockpit/CriticalBanner.vue'));
    $page = file_get_contents(base_path('resources/js/pages/Finance/Cockpit/Index.vue'));

    expect($banner)
        ->toContain('finding_code')
        ->toContain('scope')
        ->not->toContain(':href="financeRoutes.audit()"')
        ->and($page)
        ->toContain('<CriticalBanner :data-health="props.data_health" />')
        ->not->toContain('<CriticalBanner :critical-count="props.data_health?.critical_count ?? 0" />');
});

it('keeps cockpit data-health drilldowns scoped to the current campus semester', function () {
    $controller = file_get_contents(base_path('app/Modules/Finance/Http/Web/Admin/FinanceCockpitController.php'));
    $banner = file_get_contents(base_path('resources/js/components/finance/cockpit/CriticalBanner.vue'));
    $panel = file_get_contents(base_path('resources/js/components/finance/cockpit/DataHealthPanel.vue'));

    expect($controller)
        ->toContain('$this->currentCampusId(),')
        ->toContain("false,\n            \$semesterId,")
        ->not->toContain("can('view_finance_all_campus')")
        ->and($banner)
        ->toContain("scope: 'campus'")
        ->not->toContain('props.dataHealth.scope_badge')
        ->and($panel)
        ->toContain("scope: 'campus'")
        ->not->toContain('dataHealth.scope_badge');
});
