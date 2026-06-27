<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document type catalog mirrored from the CRM (slice 06).
 *
 * A read-mostly mirror of the admissions CRM's file-type catalog so Swinx can
 * label documents, order them for display, and decide which are required (for
 * everyone via `required`, or additionally for international applicants via
 * `int_required`). Synced idempotently by `code` (the CRM-owned key); the CRM
 * remains the source of truth — see ADR-0004.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('int_required')->default(false);
            $table->boolean('active')->default(true);
            $table->integer('order')->default(0);
            $table->string('step')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_document_types');
    }
};
