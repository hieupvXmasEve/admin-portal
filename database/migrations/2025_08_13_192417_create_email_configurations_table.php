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
        Schema::create('email_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Configuration name for identification');
            $table->string('host')->comment('SMTP server hostname');
            $table->integer('port')->default(587)->comment('SMTP server port');
            $table->string('username')->nullable()->comment('SMTP authentication username');
            $table->text('password')->nullable()->comment('Encrypted SMTP password');
            $table->enum('encryption', ['tls', 'ssl', 'none'])->default('tls')->comment('Encryption method');
            $table->string('from_address')->comment('Default from email address');
            $table->string('from_name')->comment('Default from name');
            $table->boolean('is_active')->default(false)->comment('Whether this configuration is active');
            $table->integer('daily_limit')->default(1000)->comment('Daily email sending limit');
            $table->integer('rate_limit')->default(60)->comment('Emails per minute limit');
            $table->timestamp('last_tested_at')->nullable()->comment('Last connection test timestamp');
            $table->text('test_result')->nullable()->comment('Last connection test result');
            $table->string('password_salt', 64)->nullable();
            $table->string('password_verification_hash')->nullable();
            $table->timestamp('password_encrypted_at')->nullable();
            $table->longText('credential_backup')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('is_active');
            $table->index('host');
            $table->index('password_encrypted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_configurations');
    }
};
