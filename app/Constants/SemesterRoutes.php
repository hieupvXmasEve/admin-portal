<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Semester Routes Constants
 * Centralized route name management for Semester module in Laravel
 */
class SemesterRoutes
{
    // Main Semester Routes
    public const INDEX = 'semesters.index';

    public const CREATE = 'semesters.create';

    public const STORE = 'semesters.store';

    public const SHOW = 'semesters.show';

    public const EDIT = 'semesters.edit';

    public const UPDATE = 'semesters.update';

    public const DESTROY = 'semesters.destroy';

    // Semester Activation Routes
    public const ACTIVATE = 'semesters.activate';

    public const DEACTIVATE = 'semesters.deactivate';

    public const ACTIVATION_STATUSES = 'semesters.activation-statuses';

    // Semester Enrollment Routes
    public const API_UPDATE = 'api.semesters.update';

    public const ENROLLMENT_SHOW = 'semesters.enrollment.show';

    // API Semester Enrollment Routes
    public const API_ENROLLMENT_GENERATE = 'api.semesters.enrollment.generate';

    // Route Prefixes
    public const WEB_PREFIX = 'semesters.';

    public const SEMESTERS_PREFIX = 'semesters.';

    public const API_PREFIX = 'api.semesters.';

    public const ENROLLMENT_PREFIX = 'enrollment.';
}
