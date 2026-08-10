<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row table (id=1) holding the CRM integration's connection details,
 * so staff can change them without a deploy. Admissions-owned — deliberately
 * not `system_settings` (Platform's closed, all-keys-required registry has
 * no place for an optional integration credential and stores values as
 * plaintext JSON). `password` is `encrypted` at the model level.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_integration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('base_url')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->unsignedInteger('timeout')->default(120);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_integration_settings');
    }
};
