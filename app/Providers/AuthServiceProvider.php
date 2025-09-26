<?php

namespace App\Providers;

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\Event;
use App\Policies\ClubMemberPolicy;
use App\Policies\ClubPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Club::class => ClubPolicy::class,
        ClubMember::class => ClubMemberPolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Permission gates are now handled by PermissionServiceProvider
        // This keeps the AuthServiceProvider focused on authentication concerns
    }
}
