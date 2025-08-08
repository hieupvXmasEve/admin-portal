<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Syllabus Routes Constants
 * Centralized route name management for Syllabus module in Laravel
 */
class SyllabusRoutes
{
    // Main Syllabus Routes
    public const INDEX = 'syllabus.index';

    public const CREATE = 'syllabus.create';

    public const STORE = 'syllabus.store';

    public const SHOW = 'syllabus.show';

    public const EDIT = 'syllabus.edit';

    public const UPDATE = 'syllabus.update';

    public const DESTROY = 'syllabus.destroy';

    public const TOGGLE_ACTIVE = 'syllabus.toggle-active';

    public const CLONE = 'syllabus.clone';

    // Syllabus Assessment Routes
    public const ASSESSMENT_INDEX = 'syllabus.assessment.index';

    public const ASSESSMENT_CREATE = 'syllabus.assessment.create';

    public const ASSESSMENT_STORE = 'syllabus.assessment.store';

    public const ASSESSMENT_UPDATE = 'syllabus.assessment.update';

    public const ASSESSMENT_DESTROY = 'syllabus.assessment.destroy';

    // Syllabus API Routes
    public const API_STORE = 'api.syllabus.store';

    public const API_UPDATE = 'api.syllabus.update';

    public const API_DESTROY = 'api.syllabus.destroy';

    // Route Prefixes
    public const WEB_PREFIX = 'syllabus.';

    public const ASSESSMENT_PREFIX = 'syllabus.assessment.';

    public const API_PREFIX = 'api.syllabus.';
}
