<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Http\Web;

/**
 * Catalog-owned HTTP boundary for the established curriculum version workflow.
 *
 * The inherited workflow is retained while its large summary projections are
 * split into Catalog Queries without changing their page contracts.
 */
class CurriculumVersionController extends \App\Http\Controllers\Web\CurriculumVersionController {}
