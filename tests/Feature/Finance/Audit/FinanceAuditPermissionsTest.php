<?php

declare(strict_types=1);

it('registers the audit workspace permission codes in config', function () {
    $codes = collect(config('permission.access'))->flatMap(fn ($actions) => array_values($actions));

    expect($codes)->toContain('view_finance_audit_workspace')
        ->and($codes)->toContain('export_finance_audit_workspace');
});
