<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->text('rendered_subject')->nullable()->after('last_error');
            $table->longText('rendered_html')->nullable()->after('rendered_subject');
            $table->text('rendered_text')->nullable()->after('rendered_html');
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->dropColumn(['rendered_subject', 'rendered_html', 'rendered_text']);
        });
    }
};
