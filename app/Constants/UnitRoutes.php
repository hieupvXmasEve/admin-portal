<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Unit Routes Constants
 * Centralized route name management for Unit module in Laravel
 */
class UnitRoutes
{
    // Main Unit Routes
    public const INDEX = 'units.index';
    public const CREATE = 'units.create';
    public const STORE = 'units.store';
    public const SHOW = 'units.show';
    public const EDIT = 'units.edit';
    public const UPDATE = 'units.update';
    public const DESTROY = 'units.destroy';

    // Unit Export Routes
    public const EXPORT_EXCEL = 'units.export.excel';
    public const EXPORT_EXCEL_FILTERED = 'units.export.excel.filtered';

    // Unit Import Routes
    public const IMPORT = 'units.import';
    public const IMPORT_UPLOAD = 'units.import.upload';
    public const IMPORT_PREVIEW = 'units.import.preview';
    public const IMPORT_PROCESS = 'units.import.process';
    public const IMPORT_TEMPLATE = 'units.import.template';
    public const IMPORT_HISTORY = 'units.import.history';

    // Unit API Routes
    public const API_SEARCH = 'api.units.search';
    public const API_VALIDATE_CODE = 'api.units.validate-code';
    public const API_VALIDATE_PREREQUISITE_EXPRESSION = 'api.units.validate-prerequisite-expression';
    public const API_BULK_DELETE = 'api.units.bulk-delete';

    // Route Prefixes
    public const WEB_PREFIX = 'units.';
    public const UNITS_PREFIX = 'units.';
    public const API_PREFIX = 'api.units.';
    public const IMPORT_PREFIX = 'import.';
    public const EXPORT_PREFIX = 'export.';
}
