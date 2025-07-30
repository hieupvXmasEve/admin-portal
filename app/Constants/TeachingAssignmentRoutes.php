<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Teaching Assignment Routes Constants
 * Centralized route name management for Teaching Assignment module in Laravel
 */
class TeachingAssignmentRoutes
{
    // Main Teaching Assignment Routes
    public const INDEX = 'teaching-assignments.index';
    public const ASSIGN = 'teaching-assignments.assign';
    public const UNASSIGN = 'teaching-assignments.unassign';
    public const AVAILABLE_LECTURERS = 'teaching-assignments.available-lecturers';
    public const CHECK_CONFLICTS = 'teaching-assignments.check-conflicts';
    public const EXPORT = 'teaching-assignments.export';

    // Teaching Assignment API Routes
    public const API_INDEX = 'api.teaching-assignments.index';
    public const API_ASSIGN = 'api.teaching-assignments.assign';
    public const API_UNASSIGN = 'api.teaching-assignments.unassign';
    public const API_AVAILABLE_LECTURERS = 'api.teaching-assignments.available-lecturers';
    public const API_CHECK_CONFLICTS = 'api.teaching-assignments.check-conflicts';
    public const API_EXPORT = 'api.teaching-assignments.export';

    // Route Prefixes
    public const WEB_PREFIX = 'teaching-assignments.';
    public const API_PREFIX = 'api.teaching-assignments.';
}
