<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

final class NotificationUrlRegistry
{
    private const ROUTES = [
        'query.student_detail' => [
            'web' => '/queries/{id}',
            'mobile' => '/queries/{id}',
        ],
        'query.admin_inbox' => [
            'web' => '/forms/admin/inbox/{id}',
            'mobile' => '/queries/{id}',
        ],
        'query.detail' => [
            'web' => '/forms/admin/inbox/{id}',
            'mobile' => '/queries/{id}',
        ],
        'finance.invoice' => [
            'web' => '/finance/invoices/{id}',
            'mobile' => '/invoices/{id}',
        ],
        'finance.dng_payment_request' => [
            'web' => '/finance/operations/batch-dng',
            'mobile' => '/finance?tab=dng',
        ],
        'academic.enrollment' => [
            'web' => '/academic/enrollments/{id}',
            'mobile' => '/enrollments/{id}',
        ],
        // Deep-links into the student portal (FE/student-nuxt) redemption
        // order detail — see docs/api/student/merchandise.md.
        'merchandise.order' => [
            'web' => '/merchandise/orders/{id}',
            'mobile' => '/merchandise/orders/{id}',
        ],
        // Staff-facing: the admin app's own redemption order review screen
        // (routes/web.php redemption-orders.show), NOT the student portal.
        'merchandise.redemption_order_review' => [
            'web' => '/redemption-orders/{id}',
        ],
        // Deep-links into the student portal (FE/student-nuxt) store product
        // detail page.
        'merchandise.catalog_item' => [
            'web' => '/merchandise/{id}',
            'mobile' => '/merchandise/{id}',
        ],
    ];

    public function resolve(string $actionType, array $params, string $platform = 'web'): ?string
    {
        $routes = self::ROUTES[$actionType] ?? null;
        if ($routes === null) {
            return null;
        }

        $pattern = $routes[$platform] ?? $routes['web'] ?? null;
        if ($pattern === null) {
            return null;
        }

        return $this->interpolate($pattern, $params);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function all(): array
    {
        return self::ROUTES;
    }

    /**
     * @return array<int, string>
     */
    public function availableTypes(): array
    {
        return array_keys(self::ROUTES);
    }

    /**
     * @return array<int, string>
     */
    public function availablePlatforms(): array
    {
        $platforms = [];
        foreach (self::ROUTES as $route) {
            $platforms = array_merge($platforms, array_keys($route));
        }

        return array_values(array_unique($platforms));
    }

    private function interpolate(string $pattern, array $params): string
    {
        $result = $pattern;
        foreach ($params as $key => $value) {
            $result = str_replace('{'.$key.'}', (string) $value, $result);
        }

        return $result;
    }
}
