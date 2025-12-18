<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Curriculum Routes Constants
 * Centralized route name management for Curriculum module in Laravel
 */
class CurriculumRoutes
{
    // Curriculum Version Routes
    public const VERSION_INDEX = 'curriculum_versions.index';

    public const VERSION_CREATE = 'curriculum_versions.create';

    public const VERSION_STORE = 'curriculum_versions.store';

    // public const VERSION_SHOW = 'curriculum_versions.show';

    public const VERSION_EDIT = 'curriculum_versions.edit';

    public const VERSION_UPDATE = 'curriculum_versions.update';

    public const VERSION_DESTROY = 'curriculum_versions.destroy';

    public const VERSION_DUPLICATE = 'curriculum_versions.duplicate';

    public const VERSION_EXPORT_FILTERED = 'curriculum_versions.export.filtered';

    // Curriculum Version Summary Tab Routes
    public const VERSION_SUMMARY_OVERVIEW = 'curriculum_versions.summary.overview';

    public const VERSION_SUMMARY_UNITS = 'curriculum_versions.summary.units';

    public const VERSION_SUMMARY_MODULES = 'curriculum_versions.summary.modules';

    public const VERSION_SUMMARY_STUDENTS = 'curriculum_versions.summary.students';

    public const VERSION_SUMMARY_ROADMAP = 'curriculum_versions.summary.roadmap';

    // Curriculum Unit Routes
    public const UNIT_INDEX = 'curriculum_unit.index';

    public const UNIT_CREATE = 'curriculum_unit.create';

    public const UNIT_STORE = 'curriculum_unit.store';

    public const UNIT_SHOW = 'curriculum_unit.show';

    public const UNIT_EDIT = 'curriculum_unit.edit';

    public const UNIT_UPDATE = 'curriculum_unit.update';

    public const UNIT_DESTROY = 'curriculum_unit.destroy';

    // Curriculum Version API Routes
    public const API_VERSION_STORE = 'api.curriculum_versions.store';

    public const API_VERSION_DESTROY = 'api.curriculum_versions.destroy';

    public const API_VERSION_SPECIALIZATIONS_BY_PROGRAM = 'api.curriculum_versions.specializations-by-program';

    public const API_VERSION_BY_PROGRAM_SPECIALIZATION = 'api.curriculum_versions.by-program-specialization';

    public const API_VERSION_BULK_DELETE = 'api.curriculum_versions.bulk-delete';

    public const API_VERSION_BULK_OPERATIONS = 'api.curriculum_versions.bulk-operations';

    // Curriculum Unit API Routes
    public const API_UNIT_STORE = 'api.curriculum-units.store';

    public const API_UNIT_UPDATE = 'api.curriculum-units.update';

    public const API_UNIT_DESTROY = 'api.curriculum-units.destroy';

    public const API_UNIT_BY_CURRICULUM_VERSION = 'api.curriculum-units.by-curriculum-version';

    public const API_UNIT_BULK_DELETE = 'api.curriculum-units.bulk-delete';

    // Route Prefixes
    public const VERSION_PREFIX = 'curriculum_versions.';

    public const UNIT_PREFIX = 'curriculum_unit.';

    public const API_VERSION_PREFIX = 'api.curriculum_versions.';

    public const API_UNIT_PREFIX = 'api.curriculum-units.';
}
