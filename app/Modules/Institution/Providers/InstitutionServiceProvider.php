<?php

declare(strict_types=1);

namespace App\Modules\Institution\Providers;

use App\Modules\Institution\Support\InstitutionCampusReferenceReader;
use App\Modules\Institution\Support\InstitutionDepartmentReferenceReader;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\Institution\DepartmentReferenceReader;
use Illuminate\Support\ServiceProvider;

class InstitutionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CampusReferenceReader::class, InstitutionCampusReferenceReader::class);
        $this->app->bind(DepartmentReferenceReader::class, InstitutionDepartmentReferenceReader::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }
}
