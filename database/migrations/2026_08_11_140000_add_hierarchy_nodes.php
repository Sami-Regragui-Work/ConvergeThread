<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function foreignKeyExists(string $table, string $constraint): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return true;
        }

        return DB::table('information_schema.REFERENTIAL_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', config('database.connections.mysql.database'))
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->exists();
    }

    public function up(): void
    {
        if (! Schema::hasColumn('role_hierarchies', 'kind')) {
            Schema::table('role_hierarchies', function (Blueprint $table) {
                $table->string('kind', 20)->default('member')->after('name');
            });
        }

        Schema::table('role_hierarchy_levels', function (Blueprint $table) {
            // MySQL refuses to drop the composite unique index while the
            // role_hierarchy_id foreign key still relies on it.
            if (Schema::getConnection()->getDriverName() === 'mysql'
                && $this->foreignKeyExists('role_hierarchy_levels', 'role_hierarchy_levels_role_hierarchy_id_foreign')) {
                $table->dropForeign('role_hierarchy_levels_role_hierarchy_id_foreign');
            }
            if (Schema::hasIndex('role_hierarchy_levels', 'role_hierarchy_levels_role_hierarchy_id_level_unique')) {
                $table->dropUnique('role_hierarchy_levels_role_hierarchy_id_level_unique');
            }

            if (! Schema::hasColumn('role_hierarchy_levels', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('role_hierarchy_id');
            }
            if (! Schema::hasColumn('role_hierarchy_levels', 'kind')) {
                $table->string('kind', 20)->default('member')->after('parent_id');
            }
            if (! Schema::hasColumn('role_hierarchy_levels', 'group_id')) {
                $table->unsignedBigInteger('group_id')->nullable()->after('label');
            }
            if (! Schema::hasColumn('role_hierarchy_levels', 'role_id')) {
                $table->unsignedBigInteger('role_id')->nullable()->after('group_id');
            }

            if (! $this->foreignKeyExists('role_hierarchy_levels', 'role_hierarchy_levels_role_hierarchy_id_foreign')) {
                $table->foreign('role_hierarchy_id')->references('id')->on('role_hierarchies')->cascadeOnDelete();
            }
            if (! Schema::hasIndex('role_hierarchy_levels', 'role_hierarchy_levels_parent_id_foreign')) {
                $table->foreign('parent_id')->references('id')->on('role_hierarchy_levels')->nullOnDelete();
            }
            if (! Schema::hasIndex('role_hierarchy_levels', 'role_hierarchy_levels_group_id_foreign')) {
                $table->foreign('group_id')->references('id')->on('groups')->nullOnDelete();
            }
            if (! Schema::hasIndex('role_hierarchy_levels', 'role_hierarchy_levels_role_id_foreign')) {
                $table->foreign('role_id')->references('id')->on('tenant_roles')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('role_hierarchy_levels', function (Blueprint $table) {
            if ($this->foreignKeyExists('role_hierarchy_levels', 'role_hierarchy_levels_role_hierarchy_id_foreign')) {
                $table->dropForeign('role_hierarchy_levels_role_hierarchy_id_foreign');
            }
            if (Schema::hasIndex('role_hierarchy_levels', 'role_hierarchy_levels_parent_id_foreign')) {
                $table->dropForeign(['parent_id']);
            }
            if (Schema::hasIndex('role_hierarchy_levels', 'role_hierarchy_levels_group_id_foreign')) {
                $table->dropForeign(['group_id']);
            }
            if (Schema::hasIndex('role_hierarchy_levels', 'role_hierarchy_levels_role_id_foreign')) {
                $table->dropForeign(['role_id']);
            }
            $table->dropColumn(['parent_id', 'kind', 'group_id', 'role_id']);
            if (! Schema::hasIndex('role_hierarchy_levels', 'role_hierarchy_levels_role_hierarchy_id_level_unique')) {
                $table->unique(['role_hierarchy_id', 'level']);
            }
            $table->foreign('role_hierarchy_id')->references('id')->on('role_hierarchies')->cascadeOnDelete();
        });

        if (Schema::hasColumn('role_hierarchies', 'kind')) {
            Schema::table('role_hierarchies', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }
    }
};
