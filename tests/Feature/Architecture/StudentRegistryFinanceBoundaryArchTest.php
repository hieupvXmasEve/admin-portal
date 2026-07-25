<?php

declare(strict_types=1);

it('keeps the Finance student search and overview tracer on the Registry contract', function (): void {
    $files = [
        base_path('app/Modules/Finance/Http/Web/Admin/FinanceGlobalSearchController.php'),
        base_path('app/Modules/Finance/Http/Web/Admin/FinanceStudentOverviewController.php'),
        base_path('app/Modules/Finance/Queries/Audit/ResolveFinanceAuditSearchQuery.php'),
        base_path('app/Modules/Finance/Queries/Audit/GetFinanceAuditGraphQuery.php'),
    ];
    $violations = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';
        if (preg_match('/use\s+App\\\\Models\\\\Student\s*;|\bStudent::|->student\b|whereHas\(\'student\'/', $contents) === 1) {
            $violations[] = $file;
        }
    }

    expect($violations)->toBeEmpty('Finance identity reads must use StudentReferenceReader outside the Registry adapter.');
});

it('keeps Finance collection and DNG identity reads on the Registry contract', function (): void {
    $files = [
        base_path('app/Modules/Finance/Models/BillingAccount.php'),
        base_path('app/Modules/Finance/Dng/Services/DngCampusCodeResolver.php'),
        base_path('app/Modules/Finance/Dng/Services/DngReservationLifecycle.php'),
        base_path('app/Modules/Finance/Dng/Services/DngPaymentService.php'),
        base_path('app/Modules/Finance/Dng/Services/DngWebhookService.php'),
        base_path('app/Modules/Finance/Dng/Services/DngReconciliationService.php'),
        base_path('app/Modules/Finance/Actions/CreateStudentDngPaymentAccessAction.php'),
        base_path('app/Modules/Finance/Services/PaymentService.php'),
        base_path('app/Modules/Finance/Queries/Dng/GetDngPaymentRequestDetailsQuery.php'),
        base_path('app/Modules/Finance/Queries/Dng/GetDngWebhookEventDetailsQuery.php'),
        base_path('app/Modules/Finance/Queries/Dng/ListDngWebhookEventsQuery.php'),
        base_path('app/Modules/Finance/Queries/Dng/ListDngPaymentRequestsQuery.php'),
        base_path('app/Modules/Finance/Queries/Dng/ListDngReceiptExceptionsQuery.php'),
        base_path('app/Modules/Finance/Queries/Dng/ListDngWorklistQuery.php'),
        base_path('app/Modules/Finance/Http/Web/Admin/DngPaymentRequestController.php'),
        base_path('app/Modules/Finance/Support/BillingAccountProvisioner.php'),
        base_path('app/Modules/Finance/Http/Web/Admin/FinanceAuditWorkspaceController.php'),
        base_path('app/Modules/Finance/Http/Web/Admin/PaymentController.php'),
    ];
    $violations = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';
        if (preg_match('/use\s+App\\\\Models\\\\Student\s*;|\bStudent::|->student\b|whereHas\(\'student\'/', $contents) === 1) {
            $violations[] = $file;
        }
    }

    expect($violations)->toBeEmpty('Finance collection and DNG identity reads must use StudentReferenceReader outside the Registry adapter.');
});

it('keeps collection eligibility behind the Registry contract', function (): void {
    $files = [
        base_path('app/Modules/Finance/Support/BillingScopeHelper.php'),
        base_path('app/Modules/Finance/Actions/Operations/GenerateBatchChargesAction.php'),
    ];
    $violations = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';
        if (preg_match('/Student::FINANCIAL_STATUSES|students\.status/', $contents) === 1) {
            $violations[] = $file;
        }
    }

    expect($violations)->toBeEmpty('Finance collection eligibility must be supplied by StudentCollectionEligibilityReader.');
});

it('keeps Billing Exception identity facts on the Registry contract', function (): void {
    $files = [
        base_path('app/Modules/Academic/Support/AcademicFinanceChargeSourceGateway.php'),
        base_path('app/Modules/Finance/Support/BillingExceptionCollector.php'),
        base_path('app/Modules/Finance/Actions/Operations/FixBillingExceptionAction.php'),
    ];
    $violations = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';
        if (preg_match('/use\s+App\\\\Models\\\\Student\s*;|(?:join|table)\(\s*[\'"]students/', $contents) === 1) {
            $violations[] = $file;
        }
    }

    expect($violations)->toBeEmpty(
        'Billing Exception identity and campus facts must use StudentReferenceReader.',
    );
});

it('keeps Billing Exception collection bounded behind the Academic owner contract', function (): void {
    $collector = file_get_contents(
        base_path('app/Modules/Finance/Support/BillingExceptionCollector.php'),
    ) ?: '';

    expect($collector)
        ->toContain('billingExceptionRegistrationChunks(')
        ->not->toContain('billingExceptionRegistrations(');
});
