<?php

declare(strict_types=1);

namespace App\Modules\Notification\EmailContent\Contracts;

/**
 * Each EmailContentProvider declares its variable allow-list via this contract.
 *
 * Single source of truth consumed by:
 *  - the FormRequest validator in P2 (rejects {{var}} not in the allow-list)
 *  - the FE "Insert variable" picker in P2 (label + sample value)
 *  - the Preview pane sample render
 *  - the parity test fixtures (S1.7)
 *
 * Keys are the bare variable name (no braces). The sample value MUST be safe
 * for HTML rendering as-is and is meant to look realistic in preview.
 */
interface EmailVariableSchema
{
    /**
     * @return array<string, array{label: string, sample: mixed}>
     *                                                            Keyed by variable name. Each entry has a human-readable label and a
     *                                                            representative sample value used in preview/test renders.
     */
    public function availableVariables(): array;
}
