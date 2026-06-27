<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents (1-n) on Applications as external link references (slice 06).
 *
 * Replaces the 8 hardcoded `submitted_*` columns with a proper 1-n list. Each row
 * is an external link reference (CRM-owned URL + metadata) — NOT a managed upload
 * and NOT an `upload_records` row (ADR-0004). Multiple files per `file_type_code`
 * are allowed (e.g. a multi-page transcript), so there is no unique index on the
 * type. Ingestion idempotency rides on `crm_file_id` (unique, nullable for
 * manually-entered references that never came from the CRM).
 *
 * The legacy `submitted_*` columns stay in place until the migration slice (09).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_application_id')
                ->constrained('student_applications')
                ->cascadeOnDelete();
            // CRM-owned external key; unique for idempotent ingestion (slice 08).
            // Nullable so staff-entered references that never came from the CRM
            // are allowed; NULLs do not collide in a unique index.
            $table->string('crm_file_id')->nullable()->unique();
            $table->string('file_type_code');
            $table->string('file_type_name')->nullable();
            $table->integer('page_index')->default(0);
            $table->string('original_name')->nullable();
            $table->text('link');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();

            $table->index('student_application_id');
            $table->index(['student_application_id', 'file_type_code'], 'app_documents_application_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_documents');
    }
};
