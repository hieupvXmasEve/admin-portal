<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->integer('parent_id')->nullable();
            $table->timestamps();
        });
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\Role::class, 'role_id')->constrained()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\Permission::class, 'permission_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('role_permissions');
    }
};
