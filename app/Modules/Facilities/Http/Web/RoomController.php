<?php

declare(strict_types=1);

namespace App\Modules\Facilities\Http\Web;

/**
 * Facilities-owned entry point for the existing Room administration flow.
 *
 * The inherited controller preserves the established staff URLs, permissions,
 * requests, and Inertia responses while Room persistence remains in place for
 * this boundary-first migration.
 */
class RoomController extends \App\Http\Controllers\Web\RoomController {}
