<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Specialization Routes Constants
 * Centralized route name management for Specialization module in Laravel
 */
class SpecializationRoutes
{
    // Main Specialization Routes
    public const INDEX = 'specializations.index';

    public const CREATE = 'specializations.create';

    public const STORE = 'specializations.store';

    public const SHOW = 'specializations.show';

    public const EDIT = 'specializations.edit';

    public const UPDATE = 'specializations.update';

    public const DESTROY = 'specializations.destroy';

    // Specialization API Routes
    public const API_DESTROY = 'api.specializations.destroy';

    public const API_BULK_DELETE = 'api.specializations.bulk-delete';

    // Curriculum Version API Routes within Specializations
    public const API_CURRICULUM_VERSION_UPDATE = 'api.curriculum_version.update';

    // Route Prefixes
    public const WEB_PREFIX = 'specializations.';

    public const API_PREFIX = 'api.specializations.';

    public const CURRICULUM_VERSION_PREFIX = 'api.curriculum_version.';
}
