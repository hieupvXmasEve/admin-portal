<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Change the campus_id foreign key on notification_email_templates
 * from cascadeOnDelete to restrictOnDelete so admin-edited rows are
 * not silently dropped when a campus is deleted.
 *
 * D2 of the dynamic-email-templates-p2 epic. Closes REV-P2-03.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_email_templates', function (Blueprint $table): void {
            $table->dropForeign(['campus_id']);
            $table->foreign('campus_id')
                ->references('id')
                ->on('campuses')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notification_email_templates', function (Blueprint $table): void {
            $table->dropForeign(['campus_id']);
            $table->foreign('campus_id')
                ->references('id')
                ->on('campuses')
                ->cascadeOnDelete();
        });
    }
};
