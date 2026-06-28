<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Authorizing-decision reference on progression events (ADR-0008).
     *
     * Records authorization provenance ("this transition was authorized by that
     * decision") for progression-stream transitions (placement / course-stage
     * change). Nullable: a requires-decision transition may be recorded first and
     * the signed quyết định attached later — it is NOT a creation-time block.
     * Mirrors the existing student_action_logs.decision_id reference.
     */
    public function up(): void
    {
        Schema::table('academic_progression_events', function (Blueprint $table) {
            $table->foreignId('decision_id')
                ->nullable()
                ->after('ielts_certificate_id')
                ->constrained('student_decisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('academic_progression_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decision_id');
        });
    }
};
