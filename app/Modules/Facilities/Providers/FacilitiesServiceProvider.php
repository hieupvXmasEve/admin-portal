<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Providers;

use App\Modules\Facilities\Support\EloquentSpaceReferenceReader;
use App\Modules\Facilities\Support\EloquentSpaceReservationService;
use App\Shared\Contracts\Facilities\SpaceAvailabilityReader;
use App\Shared\Contracts\Facilities\SpaceReferenceReader;
use App\Shared\Contracts\Facilities\SpaceReservationContract;
use Illuminate\Support\ServiceProvider;

class FacilitiesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SpaceReservationContract::class, EloquentSpaceReservationService::class);
        $this->app->bind(SpaceAvailabilityReader::class, EloquentSpaceReservationService::class);
        $this->app->bind(SpaceReferenceReader::class, EloquentSpaceReferenceReader::class);
    }

    public function boot(): void
    {
        $routesPath = __DIR__.'/../routes';

        if (file_exists($routesPath.'/web.php')) {
            $this->loadRoutesFrom($routesPath.'/web.php');
        }
    }
}
