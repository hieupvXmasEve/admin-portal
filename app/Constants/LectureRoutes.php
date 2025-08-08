<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Lecture Routes Constants
 * Centralized route name management for Lectures module in Laravel
 */
class LectureRoutes
{
    // Main Lecture Routes
    public const INDEX = 'lectures.index';

    public const CREATE = 'lectures.create';

    public const STORE = 'lectures.store';

    public const SHOW = 'lectures.show';

    public const EDIT = 'lectures.edit';

    public const UPDATE = 'lectures.update';

    public const DESTROY = 'lectures.destroy';

    // Lecture API Routes
    public const API_SEARCH = 'api.lectures.search';

    public const API_STATISTICS = 'api.lectures.statistics';

    // Route Prefixes
    public const WEB_PREFIX = 'lectures.';

    public const API_PREFIX = 'api.lectures.';
}
