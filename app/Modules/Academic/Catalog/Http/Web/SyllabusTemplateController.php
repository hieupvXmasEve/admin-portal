<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

/**
 * Catalog-owned HTTP boundary for the established syllabus template workflow.
 *
 * The inherited behavior retains its existing request, action, audit, and
 * grading-scheme contracts while the supporting action/query classes are
 * extracted in a subsequent compatibility-preserving slice.
 */
class SyllabusTemplateController extends \App\Http\Controllers\Web\SyllabusTemplateController {}
