<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('password');
            $table->string('oauth_provider')->nullable()->after('last_login_at');
            $table->string('oauth_provider_id')->nullable()->after('oauth_provider');

            $table->index(['oauth_provider', 'oauth_provider_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['oauth_provider', 'oauth_provider_id']);
            $table->dropColumn(['last_login_at', 'oauth_provider', 'oauth_provider_id']);
        });
    }
};
