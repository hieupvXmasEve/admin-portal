<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Program Routes Constants
 * Centralized route name management for Program module in Laravel
 */
class ProgramRoutes
{
    // Main Program Routes
    public const INDEX = 'programs.index';

    public const STORE = 'programs.store';

    public const SHOW = 'programs.show';

    public const UPDATE = 'programs.update';

    public const DESTROY = 'programs.destroy';

    // Program API Routes
    public const API_SEARCH = 'api.programs.search';

    public const API_BULK_DELETE = 'api.programs.bulk-delete';

    // Route Prefixes
    public const WEB_PREFIX = 'programs.';

    public const API_PREFIX = 'api.programs.';
}
