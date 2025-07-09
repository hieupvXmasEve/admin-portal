<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * User Routes Constants
 * Centralized route name management for Users module in Laravel
 */
class UserRoutes
{
    // Main User Routes
    public const INDEX = 'users.index';
    public const CREATE = 'users.create';
    public const STORE = 'users.store';
    public const SHOW = 'users.show';
    public const EDIT = 'users.edit';
    public const UPDATE = 'users.update';
    public const DESTROY = 'users.destroy';

    // User Import Routes
    public const IMPORT_FORM = 'users.import.form';
    public const IMPORT_UPLOAD = 'users.import.upload';
    public const IMPORT_PREVIEW = 'users.import.preview';
    public const IMPORT_PROCESS = 'users.import.process';
    public const IMPORT_HISTORY = 'users.import.history';
    public const IMPORT_DEBUG = 'users.import.debug';

    // User Template Routes
    public const TEMPLATE_DOWNLOAD = 'users.templates.download';

    // User Export Routes
    public const EXPORT_EXCEL = 'users.export.excel';
    public const EXPORT_EXCEL_FILTERED = 'users.export.excel.filtered';

    // Route Prefixes
    public const WEB_PREFIX = 'users.';
    public const IMPORT_PREFIX = 'import.';
    public const TEMPLATES_PREFIX = 'templates.';
    public const EXPORT_PREFIX = 'export.';
}
