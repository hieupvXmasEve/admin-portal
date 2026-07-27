<?php

declare(strict_types=1);

use App\Modules\Academic\Delivery\Actions\BackfillDecisionStudentRosterAction;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill the Decision <-> Student roster from existing
     * student_action_logs.decision_id links (ADR-0048).
     *
     * The action-log links stay intact (they remain the source of truth for which
     * transition each decision authorized); this only ensures every already-linked
     * student also appears on the decision's coverage roster. Idempotent.
     */
    public function up(): void
    {
        BackfillDecisionStudentRosterAction::run();
    }

    public function down(): void
    {
        // No-op: the roster rows are derivable and harmless to keep; the pivot
        // table itself is dropped by its own migration's down().
    }
};
