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

    // Import Routes
    public const IMPORT_FORM = 'lectures.import.form';

    public const IMPORT_UPLOAD = 'lectures.import.upload';

    public const IMPORT_PREVIEW = 'lectures.import.preview';

    public const IMPORT_PROCESS = 'lectures.import.process';

    public const TEMPLATE_DOWNLOAD = 'lectures.templates.download';

    // Export Routes
    public const EXPORT_EXCEL = 'lectures.export.excel';

    public const EXPORT_EXCEL_FILTERED = 'lectures.export.excel.filtered';

    // Teaching Hours Report
    public const TEACHING_HOURS = 'lectures.teaching-hours';

    public const TEACHING_HOURS_EXPORT = 'lectures.teaching-hours.export';

    public const TEACHING_HOURS_DETAILS = 'lectures.teaching-hours.details';

    // Route Prefixes
    public const WEB_PREFIX = 'lectures.';

    public const API_PREFIX = 'api.lectures.';
}
