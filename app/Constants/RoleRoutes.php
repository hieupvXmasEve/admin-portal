<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Role Routes Constants
 * Centralized route name management for Role module in Laravel
 */
class RoleRoutes
{
    // Main Role Routes
    public const INDEX = 'roles.index';

    public const CREATE = 'roles.create';

    public const STORE = 'roles.store';

    public const SHOW = 'roles.show';

    public const EDIT = 'roles.edit';

    public const UPDATE = 'roles.update';

    public const DESTROY = 'roles.destroy';

    // Route Prefixes
    public const WEB_PREFIX = 'roles.';
}
