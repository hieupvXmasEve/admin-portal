<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `registration_form` is the CRM's own submitted-form flag (raw `registration_form`,
 * observed as `1`), never mapped to a column before — the CRM NE sync payload
 * silently dropped it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->boolean('registration_form')->nullable()->after('crm_paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->dropColumn('registration_form');
        });
    }
};
