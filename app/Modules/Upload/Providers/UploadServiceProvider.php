<?php

declare(strict_types=1);

namespace App\Modules\Upload\Providers;

use App\Modules\Upload\Models\UploadRecord;
use App\Modules\Upload\Policies\StudentAvatarTargetPolicy;
use App\Modules\Upload\Policies\UploadRecordPolicy;
use App\Modules\Upload\Support\StudentAvatarTarget;
use App\Modules\Upload\Support\UploadFileGateway;
use App\Shared\Contracts\Upload\FileUploadGateway;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UploadServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FileUploadGateway::class, UploadFileGateway::class);
    }

    public function boot(): void
    {
        Gate::policy(UploadRecord::class, UploadRecordPolicy::class);
        Gate::policy(StudentAvatarTarget::class, StudentAvatarTargetPolicy::class);

        Route::prefix('api/uploads')
            ->middleware('api')
            ->group(__DIR__.'/../routes/api.php');
    }
}
