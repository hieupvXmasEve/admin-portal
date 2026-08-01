<?php

declare(strict_types=1);

namespace App\Modules\Merchandise\Providers;

use Illuminate\Support\ServiceProvider;

class MerchandiseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $routesPath = __DIR__.'/../routes';

        if (file_exists($routesPath.'/web.php')) {
            $this->loadRoutesFrom($routesPath.'/web.php');
        }
    }
}
