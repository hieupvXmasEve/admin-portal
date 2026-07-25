<?php

declare(strict_types=1);

namespace App\Modules\Notification\Providers;

use App\Models\Campus;
use App\Modules\Notification\Actions\PublishExternalEmailNotificationAction;
use App\Modules\Notification\Actions\UpdateEventNotificationPreferencesAction;
use App\Modules\Notification\Console\ProcessNotificationOutboxCommand;
use App\Modules\Notification\EmailContent\EmailContentRegistry;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Modules\Notification\Observers\CampusObserver;
use App\Modules\Notification\Policies\NotificationTemplatePolicy;
use App\Modules\Notification\Queries\GetEventNotificationPreferencesQuery;
use App\Modules\Notification\Support\NotificationDomainEventPublisher;
use App\Modules\Notification\Support\NotificationMetrics;
use App\Modules\Notification\Support\NotificationPayloadBuilder;
use App\Modules\Notification\Support\StudentNotificationStore;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use App\Shared\Contracts\Notification\EmailContentResolver;
use App\Shared\Contracts\Notification\ExternalEmailPublisher;
use App\Shared\Contracts\Notification\LecturerNotificationReader;
use App\Shared\Contracts\Notification\LecturerNotificationWriter;
use App\Shared\Contracts\Notification\NotificationPayloadFactory;
use App\Shared\Contracts\Notification\StudentEventNotificationPreferencesReader;
use App\Shared\Contracts\Notification\StudentEventNotificationPreferencesWriter;
use App\Shared\Contracts\Notification\StudentNotificationReader;
use App\Shared\Contracts\Notification\StudentNotificationWriter;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DomainEventPublisher::class, NotificationDomainEventPublisher::class);
        $this->app->bind(ExternalEmailPublisher::class, PublishExternalEmailNotificationAction::class);
        $this->app->bind(NotificationPayloadFactory::class, NotificationPayloadBuilder::class);
        $this->app->bind(StudentNotificationReader::class, StudentNotificationStore::class);
        $this->app->bind(StudentNotificationWriter::class, StudentNotificationStore::class);
        $this->app->bind(LecturerNotificationReader::class, StudentNotificationStore::class);
        $this->app->bind(LecturerNotificationWriter::class, StudentNotificationStore::class);
        $this->app->bind(StudentEventNotificationPreferencesReader::class, GetEventNotificationPreferencesQuery::class);
        $this->app->bind(StudentEventNotificationPreferencesWriter::class, UpdateEventNotificationPreferencesAction::class);
        $this->app->singleton(NotificationMetrics::class);
        $this->app->singleton(EmailContentRegistry::class);
        $this->app->alias(EmailContentRegistry::class, EmailContentResolver::class);
        $this->mergeConfigFrom(config_path('notification.php'), 'notification');
    }

    public function boot(): void
    {
        Campus::observe(CampusObserver::class);

        Event::listen(RequestHandled::class, function () {
            app(EmailContentRegistry::class)->reset();
        });

        Event::listen(JobProcessed::class, function () {
            app(EmailContentRegistry::class)->reset();
        });

        // Explicit registration required: Laravel 13 auto-discovery does not
        // resolve policies in module namespaces (App\Modules\*) automatically.
        Gate::policy(NotificationEmailTemplate::class, NotificationTemplatePolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessNotificationOutboxCommand::class,
            ]);
        }
    }
}
