<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Course Offering Routes Constants
 * Centralized route name management for Course Offering module in Laravel
 */
class CourseOfferingRoutes
{
    // Main Course Offering Routes
    public const INDEX = 'course-offerings.index';

    public const CREATE = 'course-offerings.create';

    public const STORE = 'course-offerings.store';

    public const SHOW = 'course-offerings.show';

    public const EDIT = 'course-offerings.edit';

    public const UPDATE = 'course-offerings.update';

    public const DESTROY = 'course-offerings.destroy';

    // Course Offering Management Routes
    public const TOGGLE_STATUS = 'course-offerings.toggle-status';

    public const SPLIT_SHOW = 'course-offerings.split.show';

    public const SPLIT_PERFORM = 'course-offerings.split.perform';

    // Course Offering API Routes
    public const API_BULK_DELETE = 'api.course-offerings.bulk-delete';

    public const API_STATISTICS = 'api.course-offerings.statistics';

    public const API_CHECK_INSTRUCTOR_ASSIGNMENTS = 'api.course-offerings.check-instructor-assignments';

    public const API_BULK_ASSIGN_LECTURES = 'api.course-offerings.bulk-assign-lectures';

    public const API_BULK_UPDATE_STATUS = 'api.course-offerings.bulk-update-status';

    // Class sessions / room management
    public const API_CHANGE_ROOM = 'api.course-offerings.change-room';

    // Route Prefixes
    public const WEB_PREFIX = 'course-offerings.';

    public const API_PREFIX = 'api.course-offerings.';

    public const SPLIT_PREFIX = 'split.';
}
