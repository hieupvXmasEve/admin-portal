<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scholarship_adjustment_dossiers', function (Blueprint $table): void {
            // Confirmation is a SEPARATE axis from the dossier's main status
            // (P4). pending|confirmed|disputed|declined|overdue — null = not yet
            // requested. DecideAdjustmentAction gates money decisions on it.
            $table->string('confirmation_status', 30)->nullable()->after('minutes_version');
            $table->dateTime('confirmation_requested_at')->nullable()->after('confirmation_status');
            $table->unsignedInteger('confirmed_minutes_version')->nullable()->after('confirmation_requested_at');
            $table->text('student_comment')->nullable()->after('confirmed_minutes_version');
            $table->dateTime('confirmed_at')->nullable()->after('student_comment');
            // Either a Student user (portal) or a staff user (on-behalf).
            $table->foreignId('confirmed_by_user_id')->nullable()->after('confirmed_at')
                ->constrained(table: 'users', indexName: 'sa_dossiers_confirmed_by_fk')
                ->nullOnDelete();
            $table->boolean('confirmed_on_behalf')->default(false)->after('confirmed_by_user_id');
            $table->text('on_behalf_note')->nullable()->after('confirmed_on_behalf');

            // The nightly overdue command filters on both columns.
            $table->index(['confirmation_status', 'confirmation_requested_at'], 'sa_dossiers_confirmation_idx');
        });
    }

    public function down(): void
    {
        Schema::table('scholarship_adjustment_dossiers', function (Blueprint $table): void {
            $table->dropIndex('sa_dossiers_confirmation_idx');
            $table->dropForeign('sa_dossiers_confirmed_by_fk');
            $table->dropColumn([
                'confirmation_status',
                'confirmation_requested_at',
                'confirmed_minutes_version',
                'student_comment',
                'confirmed_at',
                'confirmed_by_user_id',
                'confirmed_on_behalf',
                'on_behalf_note',
            ]);
        });
    }
};
