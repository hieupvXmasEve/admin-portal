<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Campus Routes Constants
 * Centralized route name management for Campus module in Laravel
 */
class CampusRoutes
{
    // Main Campus Routes
    public const INDEX = 'campuses.index';
    public const CREATE = 'campuses.create';
    public const STORE = 'campuses.store';
    public const SHOW = 'campuses.show';
    public const EDIT = 'campuses.edit';
    public const UPDATE = 'campuses.update';
    public const DESTROY = 'campuses.destroy';

    // Campus Building Routes
    public const BUILDINGS_CREATE = 'campuses.buildings.create';
    public const BUILDINGS_STORE = 'campuses.buildings.store';
    public const BUILDINGS_EDIT = 'campuses.buildings.edit';
    public const BUILDINGS_UPDATE = 'campuses.buildings.update';
    public const BUILDINGS_DESTROY = 'campuses.buildings.destroy';

    // Campus API Routes
    public const API_CAMPUSES = 'api.campuses';

    // Route Prefixes
    public const WEB_PREFIX = 'campuses.';
    public const API_PREFIX = 'api.';
    public const BUILDINGS_PREFIX = 'buildings.';
}
