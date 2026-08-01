<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A student who disputes the interview minutes must never be recorded as
 * having confirmed them. When staff have addressed the dispute and it still
 * stands, an approver overrules it explicitly — these columns keep that act
 * separate from a confirmation, and separate from the on-behalf note, so the
 * audit trail says plainly that the student disagreed and who decided anyway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scholarship_adjustment_dossiers', function (Blueprint $table): void {
            $table->timestamp('dispute_overruled_at')->nullable()->after('on_behalf_note');
            $table->unsignedBigInteger('dispute_overruled_by_user_id')->nullable()->after('dispute_overruled_at');
            $table->text('dispute_overrule_reason')->nullable()->after('dispute_overruled_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('scholarship_adjustment_dossiers', function (Blueprint $table): void {
            $table->dropColumn(['dispute_overruled_at', 'dispute_overruled_by_user_id', 'dispute_overrule_reason']);
        });
    }
};
