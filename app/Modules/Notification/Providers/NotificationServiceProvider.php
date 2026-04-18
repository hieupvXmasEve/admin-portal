<?php

declare(strict_types=1);

namespace App\Modules\Notification\Providers;

use App\Modules\Notification\Console\ProcessNotificationOutboxCommand;
use App\Modules\Notification\EmailContent\EmailContentRegistry;
use App\Modules\Notification\Support\NotificationMetrics;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationMetrics::class);
        $this->app->singleton(EmailContentRegistry::class);
        $this->mergeConfigFrom(config_path('notification.php'), 'notification');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessNotificationOutboxCommand::class,
            ]);
        }
    }
}
