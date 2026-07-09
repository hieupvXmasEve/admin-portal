<?php

declare(strict_types=1);

/**
 * ADR-0026 / issue 05: Academic and Finance money models must not cross-import.
 *
 * Money models live under App\Modules\Finance\Models. Cross-context access goes
 * only through app/Shared/Contracts/Finance.
 *
 * CI note (D1): this suite only bites once CI is re-enabled. Local/agent runs
 * should include tests/Feature/Architecture when touching the boundary.
 */
arch('Academic module never imports Finance money models')
    ->expect('App\Modules\Academic')
    ->not->toUse('App\Modules\Finance\Models');

arch('Finance money models never import Academic module')
    ->expect('App\Modules\Finance\Models')
    ->not->toUse('App\Modules\Academic');
