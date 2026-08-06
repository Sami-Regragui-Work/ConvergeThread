<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('role_hierarchy_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_hierarchy_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['role_hierarchy_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_hierarchy_levels');
    }
};
