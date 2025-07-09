<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Course Registration Routes Constants
 * Centralized route name management for Course Registration module in Laravel
 */
class CourseRegistrationRoutes
{
    // Main Course Registration Routes
    public const INDEX = 'course-registrations.index';
    public const CREATE = 'course-registrations.create';
    public const STORE = 'course-registrations.store';
    public const SHOW = 'course-registrations.show';
    public const EDIT = 'course-registrations.edit';
    public const UPDATE = 'course-registrations.update';
    public const DESTROY = 'course-registrations.destroy';

    // Course Registration Management Routes
    public const BULK_UPDATE_STATUS = 'course-registrations.bulk-update-status';
    public const BULK_DELETE = 'course-registrations.bulk-delete';
    public const EXPORT = 'course-registrations.export';

    // Course Registration API Routes
    public const API_STORE = 'api.course-registrations.store';
    public const API_UPDATE = 'api.course-registrations.update';
    public const API_DESTROY = 'api.course-registrations.destroy';
    public const API_BULK_OPERATIONS = 'api.course-registrations.bulk-operations';

    // Route Prefixes
    public const WEB_PREFIX = 'course-registrations.';
    public const API_PREFIX = 'api.course-registrations.';
}
