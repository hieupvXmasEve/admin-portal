<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM external key on the Application core (slice 08).
 *
 * `crm_admission_id` is the admissions CRM's own admission identifier. Ingestion
 * idempotency rides on it: re-sending the same admission upserts the existing
 * Application instead of duplicating it (ADR-0004). Unique and nullable —
 * manually-entered Applications that never came from the CRM carry NULL, and
 * NULLs do not collide in a unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->string('crm_admission_id')->nullable()->unique()->after('student_code');
        });
    }

    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->dropUnique(['crm_admission_id']);
            $table->dropColumn('crm_admission_id');
        });
    }
};
