<?php

declare(strict_types=1);

use App\Modules\Finance\Http\Web\Admin\FinanceAuditWorkspaceController;
use Illuminate\Support\Facades\Route;

it('keeps finance object detail routes stable for deep links and repair pages', function () {
    $detailRoutes = [
        'finance.charges.show' => [
            'uri' => 'finance/charges/{charge}',
            'middleware' => 'can:view_finance_charges',
            'parameter' => 'charge',
        ],
        'finance.invoices.show' => [
            'uri' => 'finance/invoices/{invoice}',
            'middleware' => 'can:view_finance_invoices',
            'parameter' => 'invoice',
        ],
        'finance.payments.show' => [
            'uri' => 'finance/payments/{payment}',
            'middleware' => 'can:view_finance_payment_details',
            'parameter' => 'payment',
        ],
        'finance.dng.payment-requests.show' => [
            'uri' => 'finance/dng/payment-requests/{dngPaymentRequest}',
            'middleware' => 'can:view_finance_dng_payment_requests',
            'parameter' => 'dngPaymentRequest',
        ],
    ];

    foreach ($detailRoutes as $name => $expected) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull("Detail route {$name} must remain registered")
            ->and($route->uri())->toBe($expected['uri'])
            ->and($route->methods())->toContain('GET')
            ->and($route->parameterNames())->toContain($expected['parameter'])
            ->and($route->gatherMiddleware())->toContain($expected['middleware']);
    }
});

it('keeps repair actions stricter than read-only detail permissions', function () {
    $repairRoutes = [
        'finance.charges.void' => 'can:void_finance_charges',
        'finance.charges.update-description' => 'can:create_finance_charges',
        'finance.charges.installments.split' => 'can:split_installment_finance_charges',
        'finance.charges.installments.retry-push' => 'can:split_installment_finance_charges',
        'finance.invoices.lines.void' => 'can:void_finance_charges',
        'finance.payments.allocate-preview' => 'can:allocate_finance_payment',
        'finance.payments.allocate' => 'can:allocate_finance_payment',
        'finance.dng.payment-requests.cancel-impact' => 'can:create_finance_payments',
        'finance.dng.payment-requests.cancel-reviewed' => 'can:create_finance_payments',
        'finance.dng.payment-requests.cancel' => 'can:create_finance_payments',
    ];

    foreach ($repairRoutes as $name => $middleware) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull("Repair route {$name} must remain registered")
            ->and($route->gatherMiddleware())->toContain($middleware);
    }
});

it('keeps audit workspace source links pointed at the stable detail route names', function () {
    $sourceRoutes = (new ReflectionClass(FinanceAuditWorkspaceController::class))
        ->getReflectionConstant('SOURCE_ROUTES')
        ?->getValue();

    expect($sourceRoutes)->toMatchArray([
        'invoice' => ['finance.invoices.show', 'invoice', 'view_finance_invoices'],
        'charge' => ['finance.charges.show', 'charge', 'view_finance_charges'],
        'payment' => ['finance.payments.show', 'payment', 'view_finance_payment_details'],
        'dng' => ['finance.dng.payment-requests.show', 'dngPaymentRequest', 'view_finance_dng_payment_requests'],
    ]);

    foreach (['invoice', 'charge', 'payment', 'dng'] as $type) {
        expect(Route::has($sourceRoutes[$type][0]))->toBeTrue("Audit source route {$type} must resolve");
    }
});

it('centralizes finance detail deep links in route constants and helpers', function () {
    $constants = file_get_contents(base_path('resources/js/constants/finance-routes.ts'));
    $helpers = file_get_contents(base_path('resources/js/utils/routes.ts'));

    $expectedConstants = [
        "CHARGES_SHOW: 'finance.charges.show'",
        "INVOICES_SHOW: 'finance.invoices.show'",
        "PAYMENTS_SHOW: 'finance.payments.show'",
        "DNG_PAYMENT_REQUESTS_SHOW: 'finance.dng.payment-requests.show'",
    ];

    foreach ($expectedConstants as $constant) {
        expect($constants)->toContain($constant);
    }

    $expectedHelpers = [
        'chargeDetail: (chargeId: number)',
        'invoiceDetail: (invoiceId: number)',
        'paymentDetail: (paymentId: number)',
        'dngPaymentRequestDetail: (dngPaymentRequestId: number)',
    ];

    foreach ($expectedHelpers as $helper) {
        expect($helpers)->toContain($helper);
    }
});

it('does not promote record-specific detail pages into primary sidebar navigation', function () {
    $sidebar = file_get_contents(base_path('resources/js/constants/menu-sidebar.ts'));

    foreach ([
        'finance.charges.show',
        'finance.invoices.show',
        'finance.payments.show',
        'finance.dng.payment-requests.show',
        'charges/{charge}',
        'invoices/{invoice}',
        'payments/{payment}',
        'payment-requests/{dngPaymentRequest}',
    ] as $forbiddenSidebarTarget) {
        expect($sidebar)->not->toContain($forbiddenSidebarTarget);
    }
});
