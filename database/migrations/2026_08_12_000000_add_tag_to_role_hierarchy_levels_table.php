<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('role_hierarchy_levels', 'tag')) {
            Schema::table('role_hierarchy_levels', function (Blueprint $table) {
                $table->string('tag', 40)->nullable()->after('label');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('role_hierarchy_levels', 'tag')) {
            Schema::table('role_hierarchy_levels', function (Blueprint $table) {
                $table->dropColumn('tag');
            });
        }
    }
};
