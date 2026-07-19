<?php

declare(strict_types=1);

it('keeps Finance dashboard identity and lifecycle reads on owner readers', function (): void {
    $files = [
        base_path('app/Modules/Finance/Queries/Operations/GetBillingDashboardStatsQuery.php'),
        base_path('app/Modules/Finance/Queries/Operations/GetBillingDashboardStudentsQuery.php'),
        base_path('app/Modules/Finance/Support/FinanceOperationsStudentScope.php'),
        base_path('app/Modules/Finance/Actions/Operations/GenerateNonAcademicChargesAction.php'),
        base_path('app/Modules/Finance/Http/Web/Admin/BatchStudioController.php'),
        base_path('app/Modules/Finance/Http/Web/Admin/FinanceChargeController.php'),
        base_path('app/Modules/Finance/Http/Web/Admin/FinanceStudentPaymentController.php'),
        base_path('app/Modules/Finance/Queries/Lookup/ListFinanceChargesQuery.php'),
        base_path('app/Modules/Finance/Queries/Lookup/ListStudentInvoicesQuery.php'),
        base_path('app/Modules/Finance/Queries/GetPaymentDetailsQuery.php'),
        base_path('app/Modules/Finance/Queries/Operations/ListSettlementWorklistQuery.php'),
        base_path('app/Modules/Finance/Http/Web/Admin/BillingInvoiceController.php'),
        base_path('app/Modules/Finance/Http/Export/InvoiceExport.php'),
        base_path('app/Modules/Finance/Queries/ListPaymentsQuery.php'),
        base_path('app/Modules/Finance/Http/Web/Admin/FinanceAuditWorkspaceController.php'),
        base_path('app/Modules/Finance/Queries/Audit/GetFinanceAuditGraphQuery.php'),
        base_path('app/Modules/Finance/Queries/Audit/ResolveFinanceAuditSearchQuery.php'),
    ];
    $violations = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';

        $usesStudentPersistence = preg_match('/use\s+App\\\\Models\\\\Student\s*;|\bStudent::|->student(?:\(|->)|(?:whereHas|with)\(\s*\[?[\'\"]student/', $contents) === 1;
        $usesAcademicPersistenceInScope = preg_match('/use\s+App\\\\Models\\\\CourseRegistration\s*;|\bCourseRegistration::|course_registrations/', $contents) === 1;

        if ($usesStudentPersistence || $usesAcademicPersistenceInScope) {
            $violations[] = $file;
        }
    }

    expect($violations)->toBeEmpty('Finance dashboard reads must use Registry and Progression owner readers.');
});
