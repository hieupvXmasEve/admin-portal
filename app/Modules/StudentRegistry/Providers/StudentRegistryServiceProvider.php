<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Providers;

use App\Modules\StudentRegistry\Support\EloquentStudentCollectionEligibilityReader;
use App\Modules\StudentRegistry\Support\EloquentStudentReferenceReader;
use App\Shared\Contracts\StudentRegistry\StudentCollectionEligibilityReader;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Support\ServiceProvider;

class StudentRegistryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StudentReferenceReader::class, EloquentStudentReferenceReader::class);
        $this->app->bind(StudentCollectionEligibilityReader::class, EloquentStudentCollectionEligibilityReader::class);
    }
}
