<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the per-campus, per-type_key storage backing the dynamic email
     * template contract documented in docs/features/email/template-contract.md.
     * Replaces hard-coded HTML in app/Modules/Notification/EmailContent/Types/*.
     */
    public function up(): void
    {
        Schema::create('notification_email_templates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campus_id')
                ->constrained('campuses')
                ->cascadeOnDelete();

            $table->string('type_key', 64);

            $table->string('subject', 500);

            $table->mediumText('body_html');

            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['campus_id', 'type_key'], 'notif_email_tpl_campus_type_unique');
            $table->index('type_key', 'notif_email_tpl_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_email_templates');
    }
};
