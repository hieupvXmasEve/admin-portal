<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Providers;

use Illuminate\Support\ServiceProvider;

class FacilitiesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $routesPath = __DIR__.'/../routes';

        if (file_exists($routesPath.'/web.php')) {
            $this->loadRoutesFrom($routesPath.'/web.php');
        }
    }
}
