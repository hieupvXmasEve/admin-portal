<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Club Routes Constants
 * Centralized route name management for Club module in Laravel
 */
class ClubRoutes
{
    // Main Club Routes
    public const INDEX = 'clubs.index';

    public const CREATE = 'clubs.create';

    public const STORE = 'clubs.store';

    public const SHOW = 'clubs.show';

    public const EDIT = 'clubs.edit';

    public const UPDATE = 'clubs.update';

    public const DESTROY = 'clubs.destroy';

    // Club Management Routes
    public const ASSIGN_PRESIDENT = 'clubs.assign-president';

    // Club API Routes
    public const API_STUDENTS_FOR_ASSIGNMENT = 'clubs.students-for-assignment';

    public const API_STUDENTS_FOR_CAMPUS = 'clubs.students-for-campus';

    // Route Prefixes
    public const WEB_PREFIX = 'clubs.';

    public const API_PREFIX = 'api.clubs.';
}
