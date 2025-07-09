<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Student Routes Constants
 * Centralized route name management for Student module in Laravel
 */
class StudentRoutes
{
    // Main Student Routes
    public const INDEX = 'students.index';
    public const CREATE = 'students.create';
    public const STORE = 'students.store';
    public const SHOW = 'students.show';
    public const EDIT = 'students.edit';
    public const UPDATE = 'students.update';
    public const DESTROY = 'students.destroy';

    // Student Management Actions
    public const ASSIGN_PROGRAM = 'students.assign-program';
    public const UPDATE_STATUS = 'students.update-status';

    // AJAX Student Routes
    public const AJAX_SEARCH = 'ajax.students.search';
    public const AJAX_SHOW = 'ajax.students.show';
    public const AJAX_SPECIALIZATIONS = 'ajax.students.specializations';
    public const AJAX_CURRICULUM_VERSIONS = 'ajax.students.curriculum-versions';
    public const AJAX_BY_IDS = 'ajax.students.by-ids';

    // Route Prefixes
    public const WEB_PREFIX = 'students.';
    public const AJAX_PREFIX = 'ajax.students.';
}
