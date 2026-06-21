<?php

declare(strict_types=1);

namespace App\Modules\AI\Providers;

use App\Modules\AI\Contracts\AiProviderTester;
use App\Modules\AI\Support\AiProviderCatalog;
use App\Modules\AI\Support\LaravelAiProviderResolver;
use App\Modules\AI\Support\OpenRouterAiProviderTester;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiProviderCatalog::class);
        $this->app->singleton(LaravelAiProviderResolver::class);
        $this->app->bind(AiProviderTester::class, OpenRouterAiProviderTester::class);
    }

    public function boot(): void
    {
        $routesPath = __DIR__.'/../routes';

        if (file_exists($routesPath.'/web.php')) {
            $this->loadRoutesFrom($routesPath.'/web.php');
        }
    }
}
