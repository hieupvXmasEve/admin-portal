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

    public const NEW_STUDENTS = 'students.new-students.index';
    public const BULK_ONBOARDING = 'students.new-students.bulk-onboarding';

    // Route Prefixes
    public const WEB_PREFIX = 'students.';
    public const AJAX_PREFIX = 'ajax.students.';
}
